<?php

namespace gp\Page;

defined('is_running') or die('Not an entry point...');

class Edit extends \gp\Page{

	protected $draft_file;
	protected $draft_exists			= false;
	protected $draft_stats			= array();
	protected $draft_meta			= array();
	protected $revision;

	protected $permission_edit;
	protected $permission_menu;

	private $checksum;

	public function __construct($title,$type){
		parent::__construct($title,$type);
	}


	public function RunScript(){
		global $langmessage;


		if( !$this->SetVars() ){
			return;
		}

		ob_start();

		$this->GetFile();
		$cmd = \gp\tool::GetCommand();
		$this->RunCommands($cmd);

		$this->contentBuffer = ob_get_clean();
	}


	/**
	 * Run Commands
	 *
	 */
	protected function RunCommands($cmd){

		//allow addons to effect page actions and how a page is displayed
		$cmd = \gp\tool\Plugins::Filter('PageRunScript',array($cmd));
		if( $cmd === 'return' ){
			return;
		}


		//file editing actions
		if( $this->permission_edit ){

			switch($cmd){

				case 'new_dir':
					echo \gp\tool\Editing::NewDirForm();
				return;
			}
		}

		parent::RunCommands($cmd);
	}


	/**
	 * SetVars
	 *
	 */
	public function SetVars(){
		global $dataDir, $config;

		if( !parent::SetVars() ){
			return false;
		}

		$this->permission_edit	= \gp\admin\Tools::CanEdit($this->gp_index);
		$this->permission_menu	= \gp\admin\Tools::HasPermission('Admin_Menu');
		$this->draft_file		= dirname($this->file).'/draft.php';


		//admin actions
		if( $this->permission_menu ){
			$this->cmds['RenameForm']			= '\\gp\\Page\\Rename::RenameForm';
			$this->cmds['RenameFile']			= '\\gp\\Page\\Rename::RenamePage';
			$this->cmds['ToggleVisibility']		= array('\\gp\\Page\\Visibility::TogglePage','DefaultDisplay');
		}


		if( $this->permission_edit ){

			/* gallery/image editing */
			$this->cmds['gallery_folder']		= array('GalleryImages','return');
			$this->cmds['gallery_images']		= array('GalleryImages','return');


			$this->cmds['managesections']		= '';
			$this->cmds['savesections']			= '';
			$this->cmds['viewrevision']			= '';
			$this->cmds['userevision']			= 'DefaultDisplay';
			$this->cmds['viewhistory']			= '';
			$this->cmds['viewcurrent']			= '';
			$this->cmds['deleterevision']		= 'ViewHistory';
			$this->cmds['publishdraft']			= 'DefaultDisplay';


			/* inline editing */
			$this->cmds['save']					= 'SectionEdit';
			$this->cmds['save_inline']			= 'SectionEdit';
			$this->cmds['preview']				= 'SectionEdit';
			$this->cmds['include_dialog']		= 'SectionEdit';

			$this->cmds['image_editor']			= '\\gp\\tool\\Editing::ImageEditor';
		}


		if( !\gp\tool\Files::Exists($this->draft_file) ){
			return true;
		}

		$this->draft_exists = true;


		return true;
	}


	/**
	 * Display after commands have been executed
	 *
	 */
	public function DefaultDisplay(){

		//add to all pages in case a user adds a gallery
		\gp\tool\Plugins::Action('GenerateContent_Admin');
		\gp\tool::ShowingGallery();

		$content				= '';
		$sections_count			= count($this->file_sections);
		$this->file_sections	= array_values($this->file_sections);
		$section_num			= 0;


		while( $section_num < $sections_count ){
			echo $this->GetSection( $section_num );
		}
	}


	/**
	 * Get the data file, get draft file if it exists
	 *
	 */
	public function GetFile(){

		parent::GetFile();

		if( $this->draft_exists ){
			$this->file_sections	= \gp\tool\Files::Get($this->draft_file,'file_sections');
			$this->draft_meta		= \gp\tool\Files::$last_meta;
			$this->draft_stats		= \gp\tool\Files::$last_stats;
		}

		$this->checksum				= $this->Checksum();
	}


	/**
	 * Generate admin toolbar links
	 *
	 */
	public function AdminLinks(){
		global $langmessage;


		//viewing revision
		if( isset($this->revision) ){
			return $this->RevisionLinks();
		}

		$admin_links		= array();


		//history
		$backup_files		= $this->BackupFiles();
		$times				= array_keys($backup_files);
		$admin_links[]		= \gp\tool::Link($this->title,'<i class="fa fa-backward"></i> '.$langmessage['Previous'],'cmd=ViewRevision&time='.array_pop($times),array('data-cmd'=>'cnreq'));

		$admin_links[] = \gp\tool::Link($this->title,'<i class="fa fa-history"></i> '.$langmessage['Revision History'],'cmd=ViewHistory',array('title'=>$langmessage['Revision History'],'data-cmd'=>'gpabox'));



		if( $this->permission_menu ){

			//visibility
			$q							= 'cmd=ToggleVisibility';
			$label						= '<i class="fa fa-eye-slash"></i> '.$langmessage['Visibility'].': '.$langmessage['Private'];
			if( !$this->visibility ){
				$label					= '<i class="fa fa-eye"></i> '.$langmessage['Visibility'].': '.$langmessage['Public'];
				$q						.= '&visibility=private';
			}
			$attrs						= array('title'=>$label,'data-cmd'=>'creq');
			$admin_links[]		= \gp\tool::Link($this->title,$label,$q,$attrs);
		}


		// page options: less frequently used links that don't have to do with editing the content of the page
		$option_links		= array();
		if( $this->permission_menu ){
			$option_links[] = \gp\tool::Link($this->title,$langmessage['rename/details'],'cmd=renameform&index='.urlencode($this->gp_index),'data-cmd="gpajax"');

			$option_links[] = \gp\tool::Link('Admin/Menu',$langmessage['current_layout'],'cmd=layout&from=page&index='.urlencode($this->gp_index),array('title'=>$langmessage['current_layout'],'data-cmd'=>'gpabox'));

			$option_links[] = \gp\tool::Link('Admin/Menu/Ajax',$langmessage['Copy'],'cmd=CopyForm&redir=redir&index='.urlencode($this->gp_index),array('title'=>$langmessage['Copy'],'data-cmd'=>'gpabox'));
		}

		if( \gp\admin\Tools::HasPermission('Admin_User') ){
			$option_links[] = \gp\tool::Link('Admin/Users',$langmessage['permissions'],'cmd=file_permissions&index='.urlencode($this->gp_index),array('title'=>$langmessage['permissions'],'data-cmd'=>'gpabox'));
		}

		if( $this->permission_menu ){
			$option_links[] = \gp\tool::Link('Admin/Menu/Ajax',$langmessage['delete_file'],'cmd=MoveToTrash&index='.urlencode($this->gp_index),array('data-cmd'=>'postlink','title'=>$langmessage['delete_page'],'class'=>'gpconfirm'));
		}

		if( !empty($option_links) ){
			$admin_links[$langmessage['options']] = $option_links;
		}


		//publish draft
		if( $this->permission_edit && $this->draft_exists	){
			$admin_links[] = \gp\tool::Link($this->title,'<i class="fa fa-check"></i> '.$langmessage['Publish Draft'],'cmd=PublishDraft',array('data-cmd'=>'creq', 'class'=>'msg_publish_draft'));
		}



		return array_merge($admin_links, $this->admin_links);
	}


	/**
	 * Return admin links when a revision is being displayed
	 *
	 */
	protected function RevisionLinks(){
		global $langmessage;

		$admin_links		= array();


		//previous && next revision
		$files			= $this->BackupFiles();
		$times			= array_keys($files);
		$key_current	= array_search($this->revision, $times);

		if( $key_current !== false ){
			if( isset($times[$key_current-1]) ){
				$admin_links[]		= \gp\tool::Link($this->title,'<i class="fa fa-backward"></i> '.$langmessage['Previous'],'cmd=ViewRevision&time='.$times[$key_current-1],array('data-cmd'=>'cnreq'));
			}

			if( isset($times[$key_current+1]) ){
				$admin_links[]		= \gp\tool::Link($this->title,'<i class="fa fa-forward"></i> '.$langmessage['Next'],'cmd=ViewRevision&time='.$times[$key_current+1],array('data-cmd'=>'cnreq'));
			}else{
				$admin_links[]		= \gp\tool::Link($this->title,'<i class="fa fa-forward"></i> '.$langmessage['Working Draft']);
			}

		}

		$admin_links[] = \gp\tool::Link($this->title,'<i class="fa fa-history"></i> '.$langmessage['Revision History'],'cmd=ViewHistory',array('title'=>$langmessage['Revision History'],'data-cmd'=>'gpabox'));


		// restore this version
		if( $this->revision == $this->fileModTime ){
			$date	= $langmessage['Current Page'];
		}else{
			$date	= \gp\tool::date($langmessage['strftime_datetime'],$this->revision);
		}

		$admin_links[] = \gp\tool::Link($this->title,'<i class="fa fa-save"></i> '.$langmessage['Restore this revision'].' ('.$date.')','cmd=UseRevision&time='.$this->revision,array('data-cmd'=>'cnreq','class'=>'msg_publish_draft'));



		return $admin_links;
	}


	/**
	 * Send js to client for managing content sections
	 *
	 */
	public static function ManageSections($organize = true){
		global $langmessage;

		$scripts				= array();

		//output links
		ob_start();
		$style = '';
		if( $organize ){
			$style = 'display:none';
			echo '<div id="section_sorting_wrap" class="inline_edit_area">';
			echo '<ul id="section_sorting" class="section_drag_area" title="Organize"></ul>';
			echo '<div>'.$langmessage['add'].'</div>';
			echo '<div id="new_section_links">';
			self::NewSections();
			echo '</div>';
			echo '</div>';
		}


		echo '<div id="ck_editable_areas" class="inline_edit_area" style="'.$style.'">';
		echo '<ul></ul>';
		echo '</div>';


		$scripts[]				= array('code'=>'var section_types = '.json_encode(ob_get_clean()).';');


		//selectable classes
		$avail_classes			= \gp\admin\Settings\Classes::GetClasses();
		$scripts[]				= array('code'=>'var gp_avail_classes = '.json_encode($avail_classes).';');



		$scripts[]				= array('object'=>'gp_editing','file'=>'/include/js/inline_edit/inline_editing.js');
		$scripts[]				= array('file'=>'/include/js/inline_edit/manage_sections.js');

		\gp\tool\Output\Ajax::SendScripts($scripts);
		die();
	}


	/**
	 * Send multiple sections to the client
	 *
	 */
	public function NewNestedSection($types, $wrapper_class ){
		global $langmessage;

		$new_section		= \gp\tool\Editing::DefaultContent('wrapper_section');


		$new_section['attributes']['class']		.= ' '.$wrapper_class;
		$orig_attrs								= $new_section['attributes'];


		$output = $this->SectionNode($new_section, $orig_attrs);
		foreach($types as $type){
			if( is_array($type) ){
				$_wrapper_class = isset($type[1]) ? $type[1] : '';
				$output .= $this->NewNestedSection($type[0], $_wrapper_class);
			}else{
				$output .= $this->GetNewSection($type);
			}

		}
		$output .= '</div>';

		return $output;
	}

	public function GetNewSection($type){

		$class			= self::TypeClass($type);
		$num			= time().rand(0,10000);
		$new_section	= \gp\tool\Editing::DefaultContent($type);
		$content		= \gp\tool\Output\Sections::RenderSection($new_section,$num,$this->title,$this->file_stats);

		$new_section['attributes']['class']		.= ' '.$class;
		$orig_attrs								= $new_section['attributes'];


		if( !isset($new_section['nodeName']) ){
			return $this->SectionNode($new_section, $orig_attrs).$content.'</div>';
		}

		return $this->SectionNode($new_section, $orig_attrs).$content.\gp\tool\Output\Sections::EndTag($new_section['nodeName']);
	}

	public function SectionNode($section,$orig_attrs){

		//if image type, make sure the src is a complete path
		if( $section['type'] == 'image' ){
			$orig_attrs['src'] = \gp\tool::GetDir($orig_attrs['src']);
		}

		$orig_attrs			= json_encode($orig_attrs);

		if( \gp\tool\Output::ShowEditLink() && \gp\admin\Tools::CanEdit($this->gp_index) ){
			$section['attributes']['class']			.= ' editable_area';
		}

		$section_attrs		= array('gp_label','gp_color','gp_collapse');
		foreach($section_attrs as $attr){
			if( !empty($section[$attr]) ){
				$section['attributes']['data-'.$attr] = $section[$attr];
			}
		}

		$attributes			= \gp\tool\Output\Sections::SectionAttributes($section['attributes'],$section['type']);
		$attributes			.= ' data-gp-attrs=\''.htmlspecialchars($orig_attrs,ENT_QUOTES & ~ENT_COMPAT).'\'';


		if( !isset($section['nodeName']) ){
			return '<div'.$attributes.'>';
		}

		return '<'.$section['nodeName'].$attributes.'>';
	}


	/**
	 * Save new/rearranged sections
	 *
	 */
	public function SaveSections(){
		global $langmessage;

		$this->ajaxReplace		= array();
		$original_sections		= $this->file_sections;
		$unused_sections		= $this->file_sections;				//keep track of sections that aren't used
		$new_sections			= array();


		//make sure section_order isn't empty
		if( empty($_POST['section_order']) ){
			msg($langmessage['OOPS'].' (Invalid Request)');
			return false;
		}


		foreach($_POST['section_order'] as $i => $arg ){
			$new_section 		= $this->SaveSection($i,$arg, $unused_sections);
			if( $new_section === false ){
				return false;
			}
			$new_sections[$i] = $new_section;
		}


		//make sure there's at least one section
		if( empty($new_sections) ){
			msg($langmessage['OOPS'].' (1 Section Minimum)');
			return false;
		}


		$this->file_sections = array_values($new_sections);


		// save a send message to user
		if( !$this->SaveThis() ){
			$this->file_sections = $original_sections;
			msg($langmessage['OOPS'].'(4)');
			return;
		}

		$this->ajaxReplace[] = array('ck_saved','','');


		//update gallery info
		$this->GalleryEdited();


		//update usage of resized images
		foreach($unused_sections as $section_data){
			if( isset($section_data['resized_imgs']) ){
				includeFile('image.php');
				\gp_resized::SetIndex();
				\gp\tool\Editing::ResizedImageUse($section_data['resized_imgs'],array());
			}
		}

	}


	protected function SaveSection($i,$arg, &$unused_sections ){
		global $langmessage;

		$section_types			= \gp\tool\Output\Sections::GetTypes();
		$section_attrs			= array('gp_label','gp_color','gp_collapse');

		// moved / copied sections
		if( ctype_digit($arg) ){
			$arg = (int)$arg;

			if( !isset($this->file_sections[$arg]) ){
				msg($langmessage['OOPS'].' (Invalid Section Number)');
				return false;
			}

			unset($unused_sections[$arg]);
			$new_section				= $this->file_sections[$arg];
			$new_section['attributes']	= array();

		// otherwise, new sections
		}else{

			if( !isset($section_types[$arg]) ){
				msg($langmessage['OOPS'].' (Unknown Type: '.$arg.')');
				return false;
			}
			$new_section	= \gp\tool\Editing::DefaultContent($arg);
		}

		// attributes
		$this->PostedAttributes($new_section,$i);


		// wrapper section 'contains_sections'
		if( $new_section['type'] == 'wrapper_section' ){
			$new_section['contains_sections'] = isset($_POST['contains_sections']) ? $_POST['contains_sections'][$i] : '0';
		}

		// section attributes
		foreach($section_attrs as $attr){
			unset($new_section[$attr]);
			if( !empty($_POST[$attr][$i]) ){
				$new_section[$attr]		= $_POST[$attr][$i];
			}
		}

		return $new_section;
	}


	/**
	 * Get the posted attributes for a section
	 *
	 */
	protected function PostedAttributes(&$section, $i){
		global $dirPrefix;

		if( isset($_POST['attributes'][$i]) && is_array($_POST['attributes'][$i]) ){
			foreach($_POST['attributes'][$i] as $attr_name => $attr_value){

				$attr_name		= strtolower($attr_name);
				$attr_name		= trim($attr_name);
				$attr_value		= trim($attr_value);

				if( empty($attr_name) || empty($attr_value) || $attr_name == 'id' || substr($attr_name,0,7) == 'data-gp' ){
					continue;
				}


				//strip $dirPrefix
				if( $attr_name == 'src' && !empty($dirPrefix) && strpos($attr_value,$dirPrefix) === 0 ){
					$attr_value = substr($attr_value,strlen($dirPrefix));
				}

				$section['attributes'][$attr_name] = $attr_value;
			}
		}
	}


	/**
	 * Perform various section editing commands
	 *
	 */
	public function SectionEdit(){
		global $langmessage;

		$section_num = $_REQUEST['section'];
		if( !is_numeric($section_num) || !isset($this->file_sections[$section_num])){
			echo 'false;';
			return false;
		}

		$this->ajaxReplace = array();

		$cmd = \gp\tool::GetCommand();

		if( !\gp\tool\Editing::SectionEdit( $cmd, $this->file_sections[$section_num], $section_num, $this->title, $this->file_stats ) ){
			return;
		}

		//save if the file was changed
		if( !$this->SaveThis() ){
			msg($langmessage['OOPS'].'(3)');
			return false;
		}

		$this->ajaxReplace[] = array('ck_saved','','');


		//update gallery information
		switch($this->file_sections[$section_num]['type']){
			case 'gallery':
				$this->GalleryEdited();
			break;
		}

		return true;
	}


	/**
	 * Recalculate the file_type string for this file
	 * Updates $this->meta_data and $gp_titles
	 *
	 */
	public function ResetFileTypes(){
		global $gp_titles;

		$new_types = array();
		foreach($this->file_sections as $section){
			$new_types[] = $section['type'];
		}
		$new_types = array_unique($new_types);
		$new_types = array_diff($new_types,array(''));
		sort($new_types);

		$new_types = implode(',',$new_types);
		$this->meta_data['file_type'] = $new_types;

		if( !isset($gp_titles[$this->gp_index]) ){
			return;
		}

		$gp_titles[$this->gp_index]['type'] = $new_types;
		\gp\admin\Tools::SavePagesPHP();
	}


	/**
	 * Return a list of section types
	 *
	 */
	public static function NewSections($checkboxes = false){

		$types_with_imgs	= array('text','image','gallery');

		$section_types		= \gp\tool\Output\Sections::GetTypes();
		$links				= array();
		foreach($section_types as $type => $type_info){
			$img			= '';
			if( in_array($type,$types_with_imgs) ){
				$img		= \gp\tool::GetDir('/include/imgs/section-'.$type.'.png');
			}
			$links[]		= array( $type, $img );
		}

		$links[]			= array( array('text.gpCol-6','image.gpCol-6'),\gp\tool::GetDir('/include/imgs/section-combo-text-image.png') );
		$links[]			= array( array('text.gpCol-6','gallery.gpCol-6'),\gp\tool::GetDir('/include/imgs/section-combo-text-gallery.png') );	//section combo: text & gallery

		$links				= \gp\tool\Plugins::Filter('NewSections',array($links));

		foreach($links as $link){
			$link += array('','','gpRow');
			echo self::NewSectionLink( $link[0], $link[1], $link[2], $checkboxes );
		}
	}


	/**
	 * Add link to manage section admin for nested section type
	 *
	 */
	public static function NewSectionLink($types, $img, $wrapper_class = 'gpRow', $checkbox = false ){
		global $dataDir, $page;
		static $fi = 0;

		$types			= (array)$types;
		$section_types	= \gp\tool\Output\Sections::GetTypes();
		$text_label		= array();

		foreach($types as $type){

			self::TypeClass($type);

			if( isset($section_types[$type]) ){
				$text_label[] = $section_types[$type]['label'];
			}else{
				$text_label[] = $type;
			}
		}

		$label			= '';
		if( !empty($img) ){
			$label		= '<img src="'.$img.'"/>';
		}
		$label			.= '<span>'.implode(' &amp; ',$text_label).'</span>';

		//checkbox used for new pages
		if( $checkbox ){


			if( count($types) > 1 ){
				$q		= array('types' => $types,'wrapper_class'=>$wrapper_class);
				$q		= json_encode($q);
			}else{
				$q		= $types[0];
			}


			//checked
			$checked = '';
			if( isset($_REQUEST['content_type']) && $_REQUEST['content_type'] == $q ){
				$checked = ' checked';
			}elseif( empty($_REQUEST['content_type']) && $fi === 0 ){
				$checked = ' checked';
				$fi++;
			}

			$id		= 'checkbox_'.md5($q);
			echo '<div>';
			echo '<input name="content_type" type="radio" value="'.htmlspecialchars($q).'" id="'.$id.'" required '.$checked.' />';
			echo '<label for="'.$id.'">';
			echo $label;
			echo '</label></div>';
			return;
		}

		//links used for new sections
		$attrs					= array('data-cmd'=>'AddSection','class'=>'preview_section');
		if( count($types) > 1 ){
			$attrs['data-response']	= $page->NewNestedSection($types, $wrapper_class);
		}else{
			$attrs['data-response']	= $page->GetNewSection($types[0]);
		}

		return '<div><a '.\gp\tool::LinkAttr($attrs,$label).'>'.$label.'</a></div>';
	}


	/**
	 * Split the type and class from $type = div.classname into $type = div, $class = classname
	 *
	 */
	public static function TypeClass( &$type ){

		$class = '';

		if( strpos($type,'.') ){
			list($type,$class) = explode('.',$type,2);
		}

		return $class;
	}


	/**
	 * Save the current page
	 * Save a backup if $backup is true
	 *
	 */
	public function SaveThis( $backup = true ){

		if( !is_array($this->meta_data) || !is_array($this->file_sections) ){
			return false;
		}

		//return true if nothing has changed
		if( $this->checksum === $this->Checksum() ){
			return true;
		}

		//file count
		if( !isset($this->meta_data['file_number']) ){
			$this->meta_data['file_number'] = \gp\tool\Files::NewFileNumber();
		}

		if( $backup ){
			$this->SaveBackup(); //make a backup of the page file
		}


		if( !\gp\tool\Files::SaveData($this->draft_file,'file_sections',$this->file_sections,$this->meta_data) ){
			return false;
		}

		$this->draft_exists = true;
		return true;
	}


	/**
	 * Generate a checksum for this page, used to determine if the page content has been edited
	 *
	 */
	public function Checksum(){
		$temp = array();
		foreach($this->file_sections as $section){
			unset($section['modified'], $section['modified_by']);
			$temp[] = $section;
		}
		$checksum = serialize($temp);
		return sha1($checksum) . md5($checksum);
	}


	/**
	 *	Save a backup of the file
	 *
	 */
	public function SaveBackup(){
		global $dataDir;

		$dir = $dataDir.'/data/_backup/pages/'.$this->gp_index;


		if( $this->draft_exists ){
			$contents	= \gp\tool\Files::GetRaw($this->draft_file);
		}else{
			$contents	= \gp\tool\Files::GetRaw($this->file);
		}

		//use the request time
		$time = \gp\tool\Editing::ReqTime();


		//backup file name
		$len			= strlen($contents);
		$backup_file	= $dir.'/'.$time.'.'.$len;

		if( isset($this->file_stats['username']) && $this->file_stats['username'] ){
			$backup_file .= '.'.$this->file_stats['username'];
		}

		//compress
		if( function_exists('gzencode') && function_exists('readgzfile') ){
			$backup_file .= '.gze';
			$contents = gzencode($contents,9);
		}

		if( file_exists($backup_file) ){
			return true;
		}

		if( !\gp\tool\Files::Save( $backup_file, $contents ) ){
			return false;
		}

		$this->CleanBackupFolder();
		return true;
	}


	/**
	 * Reduce the number of files in the backup folder
	 *
	 */
	public function CleanBackupFolder(){
		global $dataDir, $config;

		$files			= $this->BackupFiles();
		$file_count		= count($files);

		if( $file_count <= $config['history_limit'] ){
			return;
		}
		$delete_count = $file_count - $config['history_limit'];
		$files = array_splice( $files, 0, $delete_count );
		foreach($files as $file){
			$full_path = $dataDir.'/data/_backup/pages/'.$this->gp_index.'/'.$file;
			unlink($full_path);
		}
	}


	/**
	 * Make the working draft the live file
	 *
	 */
	public function PublishDraft(){
		global $langmessage;

		if( !$this->draft_exists ){
			msg($langmessage['OOPS'].' (Not a draft)');
			return false;
		}

		if( !\gp\tool\Files::SaveData($this->file,'file_sections',$this->file_sections,$this->draft_meta) ){
			msg($langmessage['OOPS'].' (Draft not published)');
			return false;
		}

		unlink($this->draft_file);
		$this->ResetFileTypes();
		$this->draft_exists = false;
	}


	/**
	 * Display the revision history of the current file
	 *
	 */
	public function ViewHistory(){
		global $langmessage, $config;

		$files		= $this->BackupFiles();
		$rows		= array();


		//working draft
		if( $this->draft_exists ){

			$size = filesize($this->draft_file);
			$time = $this->draft_stats['modified'];
			$rows[$time] = $this->HistoryRow($time, $size, $this->draft_stats['username'],'draft');
		}


		foreach($files as $time => $file){
			//remove .gze
			if( strpos($file,'.gze') === (strlen($file)-4) ){
				$file = substr($file,0,-4);
			}

			//get info from filename
			$name		= basename($file);
			$parts		= explode('.',$name,3);
			$time		= array_shift($parts);
			$size		= array_shift($parts);
			$username	= false;
			if( count($parts) ){
				$username = array_shift($parts);
			}

			$rows[$time] = $this->HistoryRow($time, $size, $username);
		}



		// current page
		// this will overwrite one of the history entries if there is a draft
		$rows[$this->fileModTime] = $this->HistoryRow($this->fileModTime, filesize($this->file), $this->file_stats['username'],'current');



		echo '<h2>'.$langmessage['Revision History'].'</h2>';
		echo '<table class="bordered full_width striped"><tr><th>'.$langmessage['Modified'].'</th><th>'.$langmessage['File Size'].'</th><th>'.$langmessage['username'].'</th><th>&nbsp;</th></tr>';
		echo '<tbody>';

		krsort($rows);
		echo implode('',$rows);

		echo '</tbody>';
		echo '</table>';

		echo '<p>'.$langmessage['history_limit'].': '.$config['history_limit'].'</p>';
	}


	/**
	 * Return content for history row
	 *
	 */
	protected function HistoryRow($time, $size, $username, $which = 'history' ){
		global $langmessage;

		ob_start();
		$date = \gp\tool::date($langmessage['strftime_datetime'],$time);
		echo '<tr><td title="'.htmlspecialchars($date).'">';
		switch($which){
			case 'current':
			echo '<b>'.$langmessage['Current Page'].'</b><br/>';
			break;

			case 'draft':
			echo '<b>'.$langmessage['Working Draft'].'</b><br/>';
			break;
		}

		$elapsed = \gp\admin\Tools::Elapsed(time() - $time);
		echo sprintf($langmessage['_ago'],$elapsed);
		echo '</td><td>';
		if( $size && is_numeric($size) ){
			echo \gp\admin\Tools::FormatBytes($size);
		}
		echo '</td><td>';
		if( !empty($username) ){
			echo $username;
		}
		echo '</td><td>';


		switch($which){
			case 'current':
			echo \gp\tool::Link($this->title,$langmessage['View'],'cmd=ViewCurrent',array('data-cmd'=>'cnreq'));
			break;

			case 'draft':
			echo \gp\tool::Link($this->title,$langmessage['View']);
			echo ' &nbsp; '.\gp\tool::Link($this->title,$langmessage['Publish Draft'],'cmd=PublishDraft',array('data-cmd'=>'cnreq'));
			break;

			case 'history':
			echo \gp\tool::Link($this->title,$langmessage['View'],'cmd=ViewRevision&time='.$time,array('data-cmd'=>'cnreq'));
			echo ' &nbsp; ';
			echo \gp\tool::Link($this->title,$langmessage['delete'],'cmd=DeleteRevision&time='.$time,array('data-cmd'=>'gpabox','class'=>'gpconfirm'));
			break;
		}

		echo '</td></tr>';
		return ob_get_clean();
	}


	/**
	 * Display the contents of a past revision
	 *
	 */
	protected function ViewRevision(){
		global $langmessage;

		$time			=& $_REQUEST['time'];
		$file_sections	= $this->GetRevision($time);

		if( $file_sections === false ){
			return false;
		}

		$this->revision			= $time;
		echo \gp\tool\Output\Sections::Render($file_sections,$this->title,\gp\tool\Files::$last_stats);
	}


	/**
	 * Revert the file data to a previous revision
	 *
	 */
	protected function UseRevision(){
		global $langmessage;

		$time			=& $_REQUEST['time'];
		$file_sections	= $this->GetRevision($time);

		if( $file_sections === false ){
			return false;
		}

		$this->file_sections = $file_sections;
		$this->SaveThis();
	}


	/**
	 * Get the contents of a revision
	 *
	 */
	protected function GetRevision($time){

		$full_path	= $this->BackupFile($time);

		if( is_null($full_path) ){
			return false;
		}

		//if it's a compressed file, we need an uncompressed version
		if( strpos($full_path,'.gze') !== false ){

			ob_start();
			readgzfile($full_path);
			$contents	= ob_get_clean();

			$dir		= \gp\tool::DirName($full_path);
			$full_path	= tempnam($dir,'backup').'.php';

			\gp\tool\Files::Save( $full_path, $contents );

			$file_sections	= \gp\tool\Files::Get($full_path,'file_sections');

			unlink($full_path);

		}else{
			$file_sections	= \gp\tool\Files::Get($full_path,'file_sections');
		}

		return $file_sections;
	}


	/**
	 * View the current public facing version of the file
	 *
	 */
	public function ViewCurrent(){
		$file_sections			= \gp\tool\Files::Get($this->file,'file_sections');
		$this->revision			= $this->fileModTime;
		echo \gp\tool\Output\Sections::Render($file_sections,$this->title,$this->file_stats);
	}


	/**
	 * Delete a revision backup
	 *
	 */
	public function DeleteRevision(){
		global $langmessage;

		$full_path	= $this->BackupFile($_REQUEST['time']);
		if( is_null($full_path) ){
			return false;
		}
		unlink($full_path);
	}


	/**
	 * Return a list of the available backup for the current file
	 *
	 */
	public function BackupFiles(){
		global $dataDir;
		$dir = $dataDir.'/data/_backup/pages/'.$this->gp_index;
		if( !file_exists($dir) ){
			return array();
		}
		$all_files = scandir($dir);
		$files = array();
		foreach($all_files as $file){
			if( $file == '.' || $file == '..' ){
				continue;
			}
			$parts = explode('.',$file);
			$time = array_shift($parts);
			if( !is_numeric($time) ){
				continue;
			}
			$files[$time] = $file;
		}

		ksort($files);
		return $files;
	}


	/**
	 * Return the full path of the saved revision if it exists
	 *
	 */
	public function BackupFile( $time ){
		global $dataDir;
		$files = $this->BackupFiles();
		if( !isset($files[$time]) ){
			return;
		}
		return $dataDir.'/data/_backup/pages/'.$this->gp_index.'/'.$files[$time];
	}


	/**
	 * Extract information about the gallery from it's html: img_count, icon_src
	 * Call GalleryEdited when a gallery section is removed, edited
	 *
	 */
	public function GalleryEdited(){
		\gp\special\Galleries::UpdateGalleryInfo($this->title,$this->file_sections);
	}

	public function GetSection(&$section_num){
		global $langmessage;


		if( !isset($this->file_sections[$section_num]) ){
			trigger_error('invalid section number');
			return;
		}

		$curr_section_num								= $section_num;
		$section_num++;


		$content										= '';
		$section_data									= $this->file_sections[$curr_section_num];

		//make sure section_data is an array
		$type											= gettype($section_data);
		if( $type !== 'array' ){
			trigger_error('$section_data is '.$type.'. Array expected');
			return;
		}


		$section_data									+= array('attributes' => array(),'type'=>'text' );
		$section_data['attributes']						+= array('class' => '' );
		$orig_attrs										= $section_data['attributes'];
		$section_data['attributes']['data-gp-section']	= $curr_section_num;
		$section_types									= \gp\tool\Output\Sections::GetTypes();


		if( \gp\tool\Output::ShowEditLink() && \gp\admin\Tools::CanEdit($this->gp_index) ){


			if( isset($section_types[$section_data['type']]) ){
				$title_attr		= $section_types[$section_data['type']]['label'];
			}else{
				$title_attr		= sprintf($langmessage['Section %s'],$curr_section_num+1);
			}

			$attrs			= array('title'=>$title_attr,'data-cmd'=>'inline_edit_generic','data-arg'=>$section_data['type'].'_inline_edit');
			$link			= \gp\tool\Output::EditAreaLink($edit_index,$this->title,$langmessage['edit'],'section='.$curr_section_num,$attrs);

			$section_data['attributes']['data-gp-area-id']		= $edit_index;


			//section control links
			if( $section_data['type'] != 'wrapper_section' ){
				ob_start();
				echo '<span class="nodisplay" id="ExtraEditLnks'.$edit_index.'">';
				echo $link;
				echo \gp\tool::Link($this->title,$langmessage['Manage Sections'],'cmd=ManageSections',array('class'=>'manage_sections','data-cmd'=>'inline_edit_generic','data-arg'=>'manage_sections'));
				echo '<hr/>';
				echo \gp\tool::Link($this->title,$langmessage['rename/details'],'cmd=renameform&index='.urlencode($this->gp_index),'data-cmd="gpajax"');
				echo \gp\tool::Link($this->title,$langmessage['Revision History'],'cmd=ViewHistory',array('data-cmd'=>'gpabox'));
				echo '</span>';
				\gp\tool\Output::$editlinks .= ob_get_clean();
			}

			$section_data['attributes']['id']				= 'ExtraEditArea'.$edit_index;
		}


		$content			.= $this->SectionNode($section_data, $orig_attrs);

		if( $section_data['type'] == 'wrapper_section' ){

			for( $cc=0; $cc < $section_data['contains_sections']; $cc++ ){
				$content		.= $this->GetSection($section_num);
			}

		}else{
			\gp\tool\Output::$nested_edit		= true;
			$content			.= \gp\tool\Output\Sections::RenderSection($section_data,$curr_section_num,$this->title,$this->file_stats);
			\gp\tool\Output::$nested_edit		= false;
		}

		if( !isset($section_data['nodeName']) ){
			$content			.= '<div class="gpclear"></div>';
			$content			.= '</div>';
		}else{
			$content			.= \gp\tool\Output\Sections::EndTag($section_data['nodeName']);
		}

		return $content;
	}


	public function GalleryImages(){

		if( isset($_GET['dir']) ){
			$dir_piece = $_GET['dir'];
		}elseif( isset($this->meta_data['gallery_dir']) ){
			$dir_piece = $this->meta_data['gallery_dir'];
		}else{
			$dir_piece = '/image';
		}
		//remember browse directory
		$this->meta_data['gallery_dir'] = $dir_piece;
		$this->SaveThis(false);

		\gp\admin\Content\Uploaded::InlineList($dir_piece);
	}


	/**
	 * Used by slideshow addons
	 * @deprecated 3.6rc4
	 *
	 */
	public function SaveSection_Text($section){
		return \gp\tool\Editing::SectionFromPost_Text( $this->file_sections[$section] );
	}


}
