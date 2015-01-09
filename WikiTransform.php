<?php

class WikiTransform {

	protected $transforms = array();
	public $is_dry_run = false;
	public $change_summary = "Automated edit by WikiTransform script";
	public $user = null;

	public function __construct ($path_to_wiki, $wiki_base_url, $output_root) {
		$this->path_to_wiki  = $path_to_wiki;
		$this->wiki_base_url = $wiki_base_url;
		
		self::mkdirIfNotExists($output_root);
		
		$output_dir = $output_root . '/output' . date('YmdHis',time());
		
		$dirs = array(
			'output_dir' => '',
			'new_files_dir' => '/pages',
			'raw_files_dir' => '/raw',
			'diff_files_dir' => '/diff'
		);
		
		// create properties and directories
		foreach($dirs as $dir_name => $rel_path) {
			$this->$dir_name = $output_dir . $rel_path; // e.g. ~/wikioutput/output20141116451652/pages
			self::remakeDir($this->$dir_name);
		}
	}

	static public function mkdirIfNotExists ($dir) {
		if ( ! file_exists($dir) ) {
			// FIXME: before mkdir, should check if parent dir exists...
			mkdir($dir);
		}
	}
	
	static public function remakeDir ($dir) {
		if ( file_exists($dir) ) {
			self::deleteDirContents($dir);
		}
		mkdir($dir);
	}
	
	static public function deleteDirContents ($dir) {

		$it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
		$files = new RecursiveIteratorIterator($it,
					 RecursiveIteratorIterator::CHILD_FIRST);
		foreach($files as $file) {
			if ($file->getFilename() === '.' || $file->getFilename() === '..') {
				continue;
			}
			if ($file->isDir()){
				rmdir($file->getRealPath());
			} else {
				unlink($file->getRealPath());
			}
		}
		rmdir($dir);	
	}
	
	public function importPage($id, $comment=null) {
		$out = '';
		if ( is_file($this->filepathNew($id)) ) {
			
			$out .= 'Importing: '.$this->pages[$id]['title']."\n";

			// import page
			$cmd = "php {$this->path_to_wiki}/maintenance/importTextFile.php --conf {$this->path_to_wiki}/LocalSettings.php ";

			if ( $this->pages[$id]['title'] )
				$cmd .= '--title "'. $this->pages[$id]['title'] .'" ';

			if ($comment)
				$cmd .= "--comment \"$comment\" ";
			
			if ($this->user)
				$cmd .= "--user \"{$this->user}\" ";
			
			$cmd .= '"' . $this->filepathNew($id) . '"';
		
			if ($this->is_dry_run)
				$out .= " Command: $cmd\n";
			else
				shell_exec( $cmd );
		
		}
		return $out;
	}
	
	public function addTransform ($callback) {
		$this->transforms[] = $callback;
	}
	
	public function getPageText ($id) {
		// get page text
		$cmd = "php {$this->path_to_wiki}/maintenance/getText.php "
			. "--conf {$this->path_to_wiki}/LocalSettings.php "
			. '"' . $this->pages[$id]['title'] . '"';
			
		$text = shell_exec($cmd);

		file_put_contents($this->filepathRaw($id), $text);
		
		$this->pages[$id]['text'] = $text;
		
		return $text;
	}
	
	public function loadPageList ($page_list) {
		
		$this->pages = array();
		$this->page_id_from_title = array();
		
		$pages = array();
		foreach($page_list as $p) {
			
			$id = $p['pageid'];
			$title = $p['title'];
			
			$this->page_id_from_title[$title] = $id;

			$this->pages[$id] = array(
				'title' => $title
			);
			
		}
		
	}
	
	public function execute () {
		foreach($this->pages as $id => $info) {
			$this->modifyPage($id);
		}
	}
	
	public function createDiff ($id) {
		$cmd = 'diff -u "'.$this->filepathRaw($id).'" "'.$this->filepathNew($id).'"';
	
		$diff = shell_exec($cmd);
		
		file_put_contents($this->filepathDiff($id), $diff );
	}
	
	public function filepathNew ($id) {
		return $this->new_files_dir . '/' . $id;
	}
	public function filepathRaw ($id) {
		return $this->raw_files_dir . '/' . $id;	
	}
	public function filepathDiff ($id) {
		return $this->diff_files_dir . '/' . $id . '.diff';	
	}
	
	public function modifyPage ($id) {
		$text = $this->getPageText($id); // creates file, too.
		
		file_put_contents($this->filepathNew($id), $this->executeTransforms($text) );
		
		$this->createDiff($id); 
		
		echo $this->importPage(
			$id,
			$this->change_summary
		);
	}
	
	public function executeTransforms ($text) {
		foreach($this->transforms as $fn) {
			$text = call_user_func($fn, $text);
		}
		return $text;
	}
	
	public function getPageListFromCategory ($category, $limit=5000) {
		$url = $this->wiki_base_url . 
			'/api.php?action=query&list=categorymembers&cmtitle=Category:' . 
			urlencode( $category ) . 
			'&cmsort=timestamp&cmdir=desc&cmlimit='.$limit.'&format=json';
		
		require_once 'WikiAPIcURL.php';
		$conn = new WikiAPIcURL($url, 'ndc');
		$conn->getCredentials();
		$raw_json = $conn->exe();
		
		$page_list = json_decode( $raw_json, true );
		$page_list = $page_list['query']['categorymembers'];
		$this->loadPageList( $page_list );

	}

}