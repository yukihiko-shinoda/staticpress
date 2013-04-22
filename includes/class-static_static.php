<?php
class static_static {
	private $plugin_basename;

	private $static_url;
	private $home_url;
	private $static_home_url;
	private $url_table;
	private $static_dir;
	private $post_types;

	private $transient_key = 'static static';

	private $static_files = array(
		'.html','.htm','.css','.js','.gif','.png','.jpg','.jpeg','.mp3','.zip','.ico','.ttf','.woff','.otf','.eot','.svg','.svgz','.xml'
		);

	function __construct($plugin_basename, $static_url = '/', $static_dir = ''){
		global $wpdb;

		$this->plugin_basename = $plugin_basename;

		$this->url_table = $wpdb->prefix.'urls';

		$this->init_params($static_dir, $static_url);

		add_filter('static_static::get_url', array(&$this, 'replace_url'));
		add_filter('static_static::static_url', array(&$this, 'static_url'));

		add_action('wp_ajax_static_static_init', array(&$this, 'ajax_init'));
		add_action('wp_ajax_static_static_fetch', array(&$this, 'ajax_fetch'));
		add_action('wp_ajax_static_static_finalyze', array(&$this, 'ajax_finalyze'));
	}

	private function init_params($static_dir, $static_url){
		global $wpdb;

		$parsed   = parse_url($this->get_site_url());
		$scheme   =
			isset($parsed['scheme'])
			? $parsed['scheme']
			: 'http';
		$host     = 
			isset($parsed['host'])
			? $parsed['host']
			: (defined('DOMAIN_CURRENT_SITE') ? DOMAIN_CURRENT_SITE : $_SERVER['HTTP_HOST']);
		$this->home_url = "{$scheme}://{$host}/";
		$this->static_url = preg_match('#^https?://#i', $static_url) ? $static_url : $this->home_url;
		$this->static_home_url = preg_replace('#^https?://[^/]+/#i', '/', trailingslashit($this->static_url));

		$this->static_dir = untrailingslashit(!empty($static_dir) ? $static_dir : ABSPATH);
		if (preg_match('#^https?://#i', $this->static_home_url)) {
			$this->static_dir .= preg_replace('#^https?://[^/]+/#i', '/', $this->static_home_url);
		} else {
			$this->static_dir .= $this->static_home_url;
		}
		$this->make_subdirectories($this->static_dir);

		if ($wpdb->get_var("show tables like '{$this->url_table}'") != $this->url_table)
			$this->activation();
	}

	public function activation(){
		global $wpdb;
		if ($wpdb->get_var("show tables like '{$this->url_table}'") != $this->url_table) {
			$wpdb->query("
CREATE TABLE `{$this->url_table}` (
 `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 `type` varchar(255) NOT NULL DEFAULT 'other_page',
 `url` varchar(255) NOT NULL,
 `object_id` bigint(20) unsigned NULL,
 `object_type` varchar(20) NULL ,
 `parent` bigint(20) unsigned NOT NULL DEFAULT 0,
 `pages` bigint(20) unsigned NOT NULL DEFAULT 1,
 `file_name` varchar(255) NOT NULL,
 `file_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 `last_statuscode` int(20) NULL,
 `last_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 `last_upload` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 `create_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY (`ID`),
 KEY `type` (`type`),
 KEY `url` (`url`),
 KEY `file_name` (`file_name`),
 KEY `file_date` (`file_date`),
 KEY `last_upload` (`last_upload`)
)");
		}
	}

	public function ajax_init(){
		global $wpdb;

		if (!is_user_logged_in())
			wp_die('Forbidden');

		$urls = $this->insert_all_url();
		$sql = $wpdb->prepare(
			"select type, count(*) as count from {$this->url_table} where `last_upload` < %s group by type",
			$this->fetch_start_time()
			);
		$all_urls = $wpdb->get_results($sql);

		header('Content-Type: application/json; charset=utf-8');
		if (!is_wp_error($all_urls)) {
			echo json_encode(array('result' => true, 'urls_count' => $all_urls));
		} else {
			echo json_encode(array('result' => false));
		}
		die();
	}

	public function ajax_fetch(){
		if (!is_user_logged_in())
			wp_die('Forbidden');

		$url = $this->fetch_url();
		if (!$url) {
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode(array('result' => false));
			die();
		}

		$result = array();
		$static_file = $this->create_static_file($url->url, $url->type, true, true);
		$file_count = 1;
		$result[$url->ID] = array(
			'ID' => $url->ID,
			'page' => 1,
			'type' => $url->type,
			'url' => $url->url,
			'static' => $static_file,
			);
		if ($url->pages > 1) {
			for ($page = 2; $page <= $url->pages; $page++) {
				$page_url = untrailingslashit(trim($url->url));
				$static_file = false;
				switch($url->type){
				case 'term_archive':
				case 'author_archive':
				case 'other_page':
					$page_url = sprintf('%s/page/%d', $page_url, $page);
					$static_file = $this->create_static_file($page_url, 'other_page', false, true);
					break;
				case 'single':
					$page_url = sprintf('%s/%d', $page_url, $page) . "\n";
					$static_file = $this->create_static_file($page_url, 'other_page', false, true);
					break;
				}
				if (!$static_file)
					break;
				$file_count++;
				$result["{$url->ID}-{$page}"] = array(
					'ID' => $url->ID,
					'page' => $page,
					'type' => $url->type,
					'url' => $page_url,
					'static' => $static_file,
					);
			}
		} else if ($url->type == 'front_page') {
			$page = 2;
			$page_url = sprintf('%s/page/%d', untrailingslashit(trim($url->url)), $page);
			while($static_file = $this->create_static_file($page_url, 'other_page', false, true)){
				$file_count++;
				$result["{$url->ID}-{$page}"] = array(
					'ID' => $url->ID,
					'page' => $page,
					'type' => $url->type,
					'url' => $page_url,
					'static' => $static_file,
					);
				$page++;
				$page_url = sprintf('%s/page/%d', untrailingslashit($url->url), $page);
			}
		}

		$limit = ($url->type == 'static_file' ? 100 : 10);
		while ($url = $this->fetch_url()) {
			$static_file = $this->create_static_file($url->url, $url->type, true, true);
			$file_count++;
			$result[$url->ID] = array(
				'ID' => $url->ID,
				'page' => 1,
				'type' => $url->type,
				'url' => $url->url,
				'static' => $static_file,
				);
			if ($file_count > $limit)
				break;
		}

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array('result' => true, 'files' => $result));
		die();
	}

	public function ajax_finalyze(){
		if (!is_user_logged_in())
			wp_die('Forbidden');

		$this->fetch_finalyze();

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array('result' => true));
		die();
	}

	public function replace_url($url){
		return str_replace($this->home_url, $this->static_home_url, $url);
	}

	public function static_url($permalink) {
		return urldecode(
			preg_match('/\.[^\.]+?$/i', $permalink) 
			? $permalink
			: trailingslashit(trim($permalink)) . 'index.html');
	}

	private function get_site_url(){
		global $current_blog;
		return trailingslashit(
			isset($current_blog)
			? get_home_url($current_blog->blog_id)
			: get_home_url()
			);
	}

	private function get_transient_key() {
		$current_user = function_exists('wp_get_current_user') ? wp_get_current_user() : '';
		if (isset($current_user->ID) && $current_user->ID)
			return "{$this->transient_key} - {$current_user->ID}";
		else
			return $this->transient_key;
	}

	private function fetch_start_time() {
		$transient_key = $this->get_transient_key();
		$param = get_transient($transient_key);
		if (!is_array($param))
			$param = array();
		if (isset($param['fetch_start_time'])) {
			return $param['fetch_start_time'];
		} else {
			$start_time = date('Y-m-d h:i:s', time());
			$param['fetch_start_time'] = $start_time;
			set_transient($transient_key, $param);
			return $start_time;
		}
	}

	private function fetch_last_id($next_id = false) {
		$transient_key = $this->get_transient_key();
		$param = (array)get_transient($transient_key);
		if (!is_array($param))
			$param = array();
		$last_id = isset($param['fetch_last_id']) ? intval($param['fetch_last_id']) : 0;
		if ($next_id) {
			$last_id = $next_id;
			$param['fetch_last_id'] = $next_id;
			set_transient($transient_key, $param);
		}
		return $last_id;
	}

	private function fetch_finalyze() {
		$transient_key = $this->get_transient_key();
		if (get_transient($transient_key))
			delete_transient($transient_key);
	}

	private function fetch_url() {
		global $wpdb;

		$sql = $wpdb->prepare(
			"select ID, type, url, pages from {$this->url_table} where `last_upload` < %s and ID > %d order by ID limit 1",
			$this->fetch_start_time(),
			$this->fetch_last_id()
			);
		$result = $wpdb->get_row($sql);
		if (!is_wp_error($result) && $result->ID) {
			$next_id = $this->fetch_last_id($result->ID);
			return $result;
		} else {
			$this->fetch_finalyze();
			return false;
		}
	}

	private function get_all_url() {
		global $wpdb;

		$sql = $wpdb->prepare(
			"select ID, type, url, pages from {$this->url_table} where `last_upload` < %s",
			$this->fetch_start_time()
			);
		return $wpdb->get_results($sql);
	}

	// make subdirectries
	private function make_subdirectories($file){
		$subdir = '/';
		$directories = explode('/',dirname($file));
		foreach ($directories as $dir){
			if (empty($dir))
				continue;
			$subdir .= trailingslashit($dir);
			if (!file_exists($subdir))
				mkdir($subdir, 0755);
		}
	}

	private function create_static_file($url, $file_type = 'other_page', $create_404 = true, $crawling = false) {
		$url = apply_filters('static_static::get_url', $url);
		$file_dest = untrailingslashit($this->static_dir) . $this->static_url($url);

		// get remote file
		$http_code = 200;
		switch ($file_type) {
		case 'front_page':
		case 'single':
		case 'term_archive':
		case 'author_archive':
		case 'other_page':
			if (($content = $this->remote_get($url)) && isset($content['body'])) {
				$http_code = intval($content['code']);
				switch (intval($http_code)) {
				case 200:
					if ($crawling)
						$this->other_url($content['body']);
				case 404:
					if ($create_404 || $http_code == 200) {
						$content = $this->replace_relative_URI($content['body']);
						$this->make_subdirectories($file_dest);
						file_put_contents($file_dest, $content);
						$file_date = date('Y-m-d h:i:s', filemtime($file_dest));
					}
				}
			}
			break;
		case 'static_file':
			$file_source = untrailingslashit(ABSPATH) . $url;
			if (!file_exists($file_source))
				return false;
			if ($file_source != $file_dest && (!file_exists($file_dest) || filemtime($file_source) > filemtime($file_dest)))
				$file_date = date('Y-m-d h:i:s', filemtime($file_source));
				$this->make_subdirectories($file_dest);
				copy($file_source, $file_dest);
			break;
		}
		if (file_exists($file_dest)) {
			$this->update_url(array(array(
				'type' => $file_type,
				'url' => $url,
				'file_name' => $file_dest,
				'file_date' => $file_date,
				'last_statuscode' => $http_code,
				'last_upload' => date('Y-m-d h:i:s', time()),
				)));
		} else {
			$file_dest = false;
			$this->update_url(array(array(
				'type' => $file_type,
				'url' => $url,
				'file_name' => '',
				'last_statuscode' => 404,
				'last_upload' => date('Y-m-d h:i:s', time()),
				)));
		}
		return $file_dest;
	}

	private function remote_get($url){
		if (!preg_match('#^https://#i', $url))
			$url = preg_replace('#^'.preg_quote($this->static_home_url).'#', $this->home_url, $url);
		$response = wp_remote_get($url);
		if (is_wp_error($response))
			return false;
		return array('code' => $response["response"]["code"], 'body' => $response["body"]);
	}

	private function replace_relative_URI($content) {
		$parsed = parse_url($this->home_url);
		$home_url = $parsed['scheme'] . '://' . $parsed['host'];
		if (isset($parsed['port']))
			$home_url .= ':'.$parsed['port'];
		$pattern  = array(
			'# (href|src|action)="'.preg_quote($home_url).'([^"]*)"#ism',
			"# (href|src|action)='".preg_quote($home_url)."([^']*)'#ism",
		);
		$content  = preg_replace($pattern, ' $1="$2"', $content);

		$parsed = parse_url($this->static_url);
		$static_url = $parsed['scheme'] . '://' . $parsed['host'];
		if (isset($parsed['port']))
			$static_url .= ':'.$parsed['port'];
		$pattern  = '#<(meta [^>]*property=[\'"]og:[^\'"]*[\'"] [^>]*content=|link [^>]*rel=[\'"]canonical[\'"] [^>]*href=|link [^>]*rel=[\'"]shortlink[\'"] [^>]*href=|data-href=|data-url=)[\'"](/[^\'"]*)[\'"]([^>]*)>#uism';
		$content = preg_replace($pattern, '<$1"'.$static_url.'$2"$3>', $content);

		$content = str_replace($home_url, $static_url, $content);


		return $content;
	}

	private function insert_all_url(){
		$urls = $this->get_urls();
		return $this->update_url($urls);
	}

	private function update_url($urls){
		global $wpdb;

		foreach ((array)$urls as $url){
			if (!isset($url['url']) || !$url['url'])
				continue;
			$sql = $wpdb->prepare(
				"select ID from {$this->url_table} where url=%s limit 1",
				$url['url']);
			if ($id = $wpdb->get_var($sql)){
				$sql = "update {$this->url_table}";
				$update_sql = array();
				foreach($url as $key => $val){
					$update_sql[] = $wpdb->prepare("$key = %s", $val);
				}
				$sql .= ' set '.implode(',', $update_sql);
				$sql .= $wpdb->prepare(' where ID=%s', $id);
			} else {
				$sql = "insert into {$this->url_table}";
				$sql .= ' (`' . implode('`,`', array_keys($url)). '`,`create_date`)';
				$insert_val = array();
				foreach($url as $key => $val){
					$insert_val[] = $wpdb->prepare("%s", $val);
				}
				$insert_val[] = $wpdb->prepare("%s", date('Y-m-d h:i:s'));
				$sql .= ' values (' . implode(',', $insert_val) . ')';
			}
			if ($sql)
				$wpdb->query($sql);
		}
		return $urls;
	}

	private function get_urls(){
		$this->post_types = "'".implode("','",get_post_types(array('public' => true)))."'";
		$urls = array();
		$urls = array_merge($urls, $this->front_page_url());
		$urls = array_merge($urls, $this->single_url());
		$urls = array_merge($urls, $this->terms_url());
		$urls = array_merge($urls, $this->author_url());
		$urls = array_merge($urls, $this->static_files_url());
		return $urls;
	}

	private function front_page_url($url_type = 'front_page'){
		$urls = array();
		$site_url = $this->get_site_url();
		$urls[] = array(
			'type' => $url_type,
			'url' => apply_filters('static_static::get_url', $site_url),
			'last_modified' => date('Y-m-d h:i:s'),
			);
		return $urls;
	}

	private function single_url($url_type = 'single') {
		global $wpdb;

		if (!isset($this->post_types) || empty($this->post_types))
			$this->post_types = "'".implode("','",get_post_types(array('public' => true)))."'";

		$urls = array();
		$posts = $wpdb->get_results("
select ID, post_type, post_content, post_status, post_modified
 from {$wpdb->posts}
 where (post_status = 'publish' or post_type = 'attachment')
 and post_type in ({$this->post_types})
 order by post_type, ID
");
		foreach ($posts as $post) {
			$post_id = $post->ID;
			$modified = $post->post_modified;
			$permalink = get_permalink($post->ID);
			if (is_wp_error($permalink))
				continue;
			$permalink = str_replace($home_url, '', $permalink);
			$count = 1;
			if ( $splite = preg_split("#<!--nextpage-->#", $post->post_content) )
				$count = count($splite);
			$urls[] = array(
				'type' => $url_type,
				'url' => apply_filters('static_static::get_url', $permalink),
				'object_id' => intval($post_id),
				'object_type' =>  $post->post_type,
				'pages' => $count,
				'last_modified' => $modified,
				);
		}
		return $urls;
	}

	private function get_term_info($term_id) {
		global $wpdb;

		if (!isset($this->post_types) || empty($this->post_types))
			$this->post_types = "'".implode("','",get_post_types(array('public' => true)))."'";

		$result = $wpdb->get_row($wpdb->prepare("
select MAX(P.post_modified) as last_modified, count(P.ID) as count
 from {$wpdb->posts} as P
 inner join {$wpdb->term_relationships} as tr on tr.object_id = P.ID
 inner join {$wpdb->term_taxonomy} as tt on tt.term_taxonomy_id = tr.term_taxonomy_id
 where P.post_status = %s and P.post_type in ({$this->post_types})
  and tt.term_id = %d
",
			'publish',
			intval($term_id)
			));
		if (!is_wp_error($result)) {
			$modified = $result->last_modified;
			$count = $result->count;
		} else {
			$modified = date('Y-m-d h:i:s');
			$count = 1;
		}
		$page_count = intval($count / intval(get_option('posts_per_page'))) + 1;
		return array($modified, $page_count);
	}

	private function terms_url($url_type = 'term_archive') {
		global $wpdb;

		$urls = array();
		$taxonomies = get_taxonomies(array('public'=>true));
		foreach($taxonomies as $taxonomy) {
			$terms = get_terms($taxonomy);
			if (is_wp_error($terms))
				continue;
			foreach ($terms as $term){
				$term_id = $term->term_id;
				$termlink = get_term_link($term->slug, $taxonomy);
				if (is_wp_error($termlink))
					continue;
				$termlink = str_replace($home_url, '', $termlink);
				list($modified, $page_count) = $this->get_term_info($term_id);
				$urls[] = array(
					'type' => $url_type,
					'url' => apply_filters('static_static::get_url', $termlink),
					'object_id' => intval($term_id),
					'object_type' => $term->taxonomy,
					'parent' => $term->parent,
					'pages' => $page_count,
					'last_modified' => $modified,
					);

				$termchildren = get_term_children($term->term_id, $taxonomy);
				if (is_wp_error($termchildren))
					continue;
				foreach ( $termchildren as $child ) {
					$term = get_term_by('id', $child, $taxonomy);
					$term_id = $term->term_id;
					if (is_wp_error($term))
						continue;
					$termlink = get_term_link($term->name, $taxonomy);
					if (is_wp_error($termlink))
						continue;
					$termlink = str_replace($home_url, '', $termlink);
					list($modified, $page_count) = $this->get_term_info($term_id);
					$urls[] = array(
						'type' => $url_type,
						'url' => apply_filters('static_static::get_url', $termlink),
						'object_id' => intval($term_id),
						'object_type' => $term->taxonomy,
						'parent' => $term->parent,
						'pages' => $page_count,
						'last_modified' => $modified,
						);
				}
			}
		}
		return $urls;
	}

	private function author_url($url_type = 'author_archive') {
		global $wpdb;

		if (!isset($this->post_types) || empty($this->post_types))
			$this->post_types = "'".implode("','",get_post_types(array('public' => true)))."'";

		$urls = array();

		$authors = $wpdb->get_results("
SELECT DISTINCT post_author, COUNT(ID) AS count, MAX(post_modified) AS modified
 FROM {$wpdb->posts} 
 where post_status = 'publish'
   and post_type in ({$this->post_types})
 group by post_author
 order by post_author
");
		foreach ($authors as $author) {
			$author_id = $author->post_author;
			$page_count = intval($author->count / intval(get_option('posts_per_page'))) + 1;
			$modified = $author->modified;
			$author = get_userdata($author_id);
			if (is_wp_error($author))
				continue;
			$authorlink = get_author_posts_url($author->ID, $author->user_nicename);
			if (is_wp_error($authorlink))
				continue;
			$urls[] = array(
				'type' => $url_type,
				'url' => apply_filters('static_static::get_url', $authorlink),
				'object_id' => intval($author_id),
				'pages' => $page_count,
				'last_modified' => $modified,
				);
		}
		return $urls;
	}

	private function static_files_url($url_type = 'static_file'){
		$urls = array();

		$static_files = $this->static_files;
		foreach ($static_files as &$static_file) {
			$static_file = "*{$static_file}";
		}
		$static_files = $this->scan_file(ABSPATH, '{'.implode(',',$static_files).'}');
		foreach ($static_files as $static_file){
			$urls[] = array(
				'type' => $url_type,
				'url' => apply_filters('static_static::get_url', str_replace(ABSPATH, '/', $static_file)),
				'last_modified' => date('Y-m-d h:i:s', filemtime($static_file)),
				);
		}
		return $urls;
	}

	private function other_url($content){
		global $wpdb;

		$urls = array();
		$pattern = '#href=[\'"](' . preg_quote($this->get_site_url()) . '[^\'"\?\#]+)[^\'"]*[\'"]#i';
		if ( preg_match_all($pattern, $content, $matches) ){
			$matches = array_unique($matches[1]);
			foreach ($matches as $link) {
				$link = apply_filters('static_static::get_url', $link);
				$sql = $wpdb->prepare(
					"select count(*) from {$this->url_table} where `url` = %s limit 1",
					$link
					);
				$count = intval($wpdb->get_var($sql));
				if ($count === 0) {
					$urls[] = array(
						'url' => $link,
						'last_modified' => date('Y-m-d h:i:s'),
						);
				}
			}
		}
		unset($matches);
		if (count($urls) > 0)
			$this->update_url($urls);
		return $urls;
	}

	private function scan_file($dir, $target = '{*.html,*.htm,*.css,*.js,*.gif,*.png,*.jpg,*.jpeg,*.zip,*.ico,*.ttf,*.woff,*.otf,*.eot,*.svg,*.svgz,*.xml}') {
		$list = $tmp = array();
		foreach(glob($dir . '*/', GLOB_ONLYDIR) as $child_dir) {
			if ($tmp = $this->scan_file($child_dir, $target)) {
				$list = array_merge($list, $tmp);
			}
		}

		foreach(glob($dir . $target, GLOB_BRACE) as $image) {
			$list[] = $image;
		}

		return $list;
	}
}