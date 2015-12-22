<?php

namespace gp\admin;

defined('is_running') or die('Not an entry point...');
defined('gp_max_menu_level') or define('gp_max_menu_level',6);

includeFile('tool/SectionContent.php');
\common::LoadComponents('sortable');


class Menu extends \gp\admin\Menu\Search{

	public $cookie_settings		= array();
	public $hidden_levels			= array();
	public $search_page			= 0;
	public $search_max_per_page	= 20;
	public $query_string;

	public $avail_menus			= array();
	public $curr_menu_id;
	public $curr_menu_array		= false;
	public $is_alt_menu			= false;
	public $max_level_index		= 3;

	public $main_menu_count;
	public $list_displays			= array('search'=>true, 'all'=>true, 'hidden'=>true, 'nomenus'=>true );

	public $section_types;


	public function __construct(){
		global $langmessage,$page,$config;


		$this->section_types			= \section_content::GetTypes();

		$page->ajaxReplace				= array();

		$page->css_admin[]				= '/include/css/admin_menu_new.css';

		$page->head_js[]				= '/include/thirdparty/js/nestedSortable.js';
		$page->head_js[]				= '/include/thirdparty/js/jquery_cookie.js';
		$page->head_js[]				= '/include/js/admin_menu_new.js';

		$this->max_level_index			= max(3,gp_max_menu_level-1);
		$page->head_script				.= 'var max_level_index = '.$this->max_level_index.';';


		$this->avail_menus['gpmenu']	= $langmessage['Main Menu'].' / '.$langmessage['site_map'];
		$this->avail_menus['all']		= $langmessage['All Pages'];
		$this->avail_menus['hidden']	= $langmessage['Not In Main Menu'];
		$this->avail_menus['nomenus']	= $langmessage['Not In Any Menus'];
		$this->avail_menus['search']	= $langmessage['search pages'];

		if( isset($config['menus']) ){
			foreach($config['menus'] as $id => $menu_label){
				$this->avail_menus[$id] = $menu_label;
			}
		}

	}

	function RunScript(){

		//read cookie settings
		if( isset($_COOKIE['gp_menu_prefs']) ){
			parse_str( $_COOKIE['gp_menu_prefs'] , $this->cookie_settings );
		}

		$this->SetMenuID();
		$this->SetMenuArray();
		$this->SetCollapseSettings();
		$this->SetQueryInfo();

		$cmd		= \common::GetCommand();
		$cmd_after	= \gpPlugin::Filter('MenuCommand',array($cmd));

		if( $cmd !== $cmd_after ){
			$cmd = $cmd_after;
			if( $cmd === 'return' ){
				return;
			}
		}

		switch($cmd){

			case 'homepage_select':
				$this->HomepageSelect();
			return;
			case 'homepage_save':
				$this->HomepageSave();
			return;

			case 'ToggleVisibility':
				$this->ToggleVisibility();
			break;

			//rename
			case 'renameform':
				$this->RenameForm(); //will die()
			return;

			case 'renameit':
				$this->RenameFile();
			break;

			case 'hide':
				$this->Hide();
			break;

			case 'drag':
				$this->SaveDrag();
			break;

			case 'trash_page';
			case 'trash':
				$this->MoveToTrash($cmd);
			break;

			case 'add_hidden':
				$this->AddHidden();
			return;
			case 'new_hidden':
				$this->NewHiddenFile();
			break;

			case 'CopyPage':
				$this->CopyPage();
			break;
			case 'copypage':
				$this->CopyForm();
			return;

			// Page Insertion
			case 'insert_before':
			case 'insert_after':
			case 'insert_child':
				$this->InsertDialog($cmd);
			return;

			case 'restore':
				$this->RestoreFromTrash();
			break;

			case 'insert_from_hidden';
				$this->InsertFromHidden();
			break;

			case 'new_file':
				$this->NewFile();
			break;

			//layout
			case 'layout':
			case 'uselayout':
			case 'restorelayout':
				includeFile('tool/Page_Layout.php');
				$page_layout = new page_layout($cmd,'Admin/Menu',$this->query_string);
				if( $page_layout->result() ){
					return;
				}
			break;


			//external links
			case 'new_external':
				$this->NewExternal();
			break;
			case 'edit_external':
				$this->EditExternal();
			return;
			case 'save_external':
				$this->SaveExternal();
			break;


		}

		$this->ShowForm($cmd);

	}

	public function Link($href,$label,$query='',$attr='',$nonce_action=false){
		$query = $this->MenuQuery($query);
		return \common::Link($href,$label,$query,$attr,$nonce_action);
	}

	public function GetUrl($href,$query='',$ampersands=true){
		$query = $this->MenuQuery($query);
		return \common::GetUrl($href,$query,$ampersands);
	}

	public function MenuQuery($query=''){
		if( !empty($query) ){
			$query .= '&';
		}
		$query .= 'menu='.$this->curr_menu_id;
		if( strpos($query,'page=') !== false ){
			//do nothing
		}elseif( $this->search_page > 0 ){
			$query .= '&page='.$this->search_page;
		}

		//for searches
		if( !empty($_REQUEST['q']) ){
			$query .= '&q='.urlencode($_REQUEST['q']);
		}

		return $query;
	}

	public function SetQueryInfo(){

		//search page
		if( isset($_REQUEST['page']) && is_numeric($_REQUEST['page']) ){
			$this->search_page = (int)$_REQUEST['page'];
		}

		//browse query string
		$this->query_string = $this->MenuQuery();
	}

	public function SetCollapseSettings(){
		$gp_menu_collapse =& $_COOKIE['gp_menu_hide'];

		$search = '#'.$this->curr_menu_id.'=[';
		$pos = strpos($gp_menu_collapse,$search);
		if( $pos === false ){
			return;
		}

		$gp_menu_collapse = substr($gp_menu_collapse,$pos+strlen($search));
		$pos = strpos($gp_menu_collapse,']');
		if( $pos === false ){
			return;
		}
		$gp_menu_collapse = substr($gp_menu_collapse,0,$pos);
		$gp_menu_collapse = trim($gp_menu_collapse,',');
		$this->hidden_levels = explode(',',$gp_menu_collapse);
		$this->hidden_levels = array_flip($this->hidden_levels);
	}



	/**
	 * Get the id for the current menu
	 * Not the same order as used for $_REQUEST
	 *
	 */
	public function SetMenuID(){

		if( isset($this->curr_menu_id) ){
			return;
		}

		if( isset($_POST['menu']) ){
			$this->curr_menu_id = $_POST['menu'];
		}elseif( isset($_GET['menu']) ){
			$this->curr_menu_id = $_GET['menu'];
		}elseif( isset($this->cookie_settings['gp_menu_select']) ){
			$this->curr_menu_id = $this->cookie_settings['gp_menu_select'];
		}

		if( !isset($this->curr_menu_id) || !isset($this->avail_menus[$this->curr_menu_id]) ){
			$this->curr_menu_id = 'gpmenu';
		}

	}

	public function SetMenuArray(){
		global $gp_menu;

		if( isset($this->list_displays[$this->curr_menu_id]) ){
			return;
		}

		//set curr_menu_array
		if( $this->curr_menu_id == 'gpmenu' ){
			$this->curr_menu_array =& $gp_menu;
			$this->is_main_menu = true;
			return;
		}

		$this->curr_menu_array = \gpOutput::GetMenuArray($this->curr_menu_id);
		$this->is_alt_menu = true;
	}


	public function SaveMenu($menu_and_pages=false){
		global $dataDir;

		if( $this->is_main_menu ){
			return \admin_tools::SavePagesPHP();
		}

		if( $this->curr_menu_array === false ){
			return false;
		}

		if( $menu_and_pages && !\admin_tools::SavePagesPHP() ){
			return false;
		}

		$menu_file = $dataDir.'/data/_menus/'.$this->curr_menu_id.'.php';
		return \gpFiles::SaveData($menu_file,'menu',$this->curr_menu_array);
	}




	/**
	 * Primary Display
	 *
	 *
	 */
	public function ShowForm(){
		global $langmessage, $page, $config;


		$replace_id = '';
		$menu_output = false;
		ob_start();

		if( isset($this->list_displays[$this->curr_menu_id]) ){
			$this->SearchDisplay();
			$replace_id = '#gp_menu_available';
		}else{
			$menu_output = true;
			$this->OutputMenu();
			$replace_id = '#admin_menu';
		}

		$content = ob_get_clean();


		// json response
		if( isset($_REQUEST['gpreq']) && ($_REQUEST['gpreq'] == 'json') ){
			$this->MenuJsonResponse( $replace_id, $content);
			return;
		}


		// search form
		echo '<form action="'.\common::GetUrl('Admin/Menu').'" method="post" id="page_search">';
		$_REQUEST += array('q'=>'');
		echo '<input type="text" name="q" size="15" value="'.htmlspecialchars($_REQUEST['q']).'" class="gptext gpinput title-autocomplete" /> ';
		echo '<input type="submit" name="cmd" value="'.$langmessage['search pages'].'" class="gpbutton" />';
		echo '<input type="hidden" name="menu" value="search" />';
		echo '</form>';


		$menus = $this->GetAvailMenus('menu');
		$lists = $this->GetAvailMenus('display');


		//heading
		echo '<form action="'.\common::GetUrl('Admin/Menu').'" method="post" id="gp_menu_select_form">';
		echo '<input type="hidden" name="curr_menu" id="gp_curr_menu" value="'.$this->curr_menu_id.'" />';

		echo '<h2 class="first-child">';
		echo $langmessage['file_manager'].' &#187;  ';
		echo '<select id="gp_menu_select" name="gp_menu_select" class="gpselect">';

		echo '<optgroup label="'.$langmessage['Menus'].'">';
			foreach($menus as $menu_id => $menu_label){
				if( $menu_id == $this->curr_menu_id ){
					echo '<option value="'.$menu_id.'" selected="selected">';
				}else{
					echo '<option value="'.$menu_id.'">';
				}
				echo $menu_label.'</option>';
			}
		echo '</optgroup>';
		echo '<optgroup label="'.$langmessage['Lists'].'">';
			foreach($lists as $menu_id => $menu_label){

				if( $menu_id == $this->curr_menu_id ){
					echo '<option value="'.$menu_id.'" selected="selected">';
				}elseif( $menu_id == 'search' ){
					continue;
				}else{
					echo '<option value="'.$menu_id.'">';
				}
				echo $menu_label.'</option>';
			}
		echo '</optgroup>';
		echo '</select>';
		echo '</h2>';

		echo '</form>';


		//homepage
		echo '<div class="homepage_setting">';
		$this->HomepageDisplay();
		echo '</div>';
		\gp_edit::PrepAutoComplete();





		echo '<div id="admin_menu_div">';

		if( $menu_output ){
			echo '<ul id="admin_menu" class="sortable_menu">';
			echo $content;
			echo '</ul><div id="admin_menu_tools" ></div>';

			echo '<div id="menu_info" style="display:none">';
			$this->MenuSkeleton();
			echo '</div>';

			echo '<div id="menu_info_extern" style="display:none">';
			$this->MenuSkeletonExtern();
			echo '</div>';

		}else{
			echo '<div id="gp_menu_available">';
			echo $content;
			echo '</div>';
		}

		echo '</div>';


		echo '<div class="admin_footnote">';

		echo '<div>';
		echo '<b>'.$langmessage['Menus'].'</b>';
		foreach($menus as $menu_id => $menu_label){
			if( $menu_id == $this->curr_menu_id ){
				echo '<span>'.$menu_label.'</span>';
			}else{
				echo '<span>'.\common::Link('Admin/Menu',$menu_label,'menu='.$menu_id, array('data-cmd'=>'cnreq')).'</span>';
			}

		}
		echo '<span>'.\common::Link('Admin/Menu/Menus','+ '.$langmessage['Add New Menu'],'cmd=NewMenuPrompt','data-cmd="gpabox"').'</span>';
		echo '</div>';

		echo '<div>';
		echo '<b>'.$langmessage['Lists'].'</b>';
		foreach($lists as $menu_id => $menu_label){
			if( $menu_id == $this->curr_menu_id ){
			}else{
			}
			echo '<span>'.\common::Link('Admin/Menu',$menu_label,'menu='.$menu_id,array('data-cmd'=>'creq')).'</span>';
		}
		echo '</div>';


		//options for alternate menu
		if( $this->is_alt_menu ){
			echo '<div>';
			$label = $menus[$this->curr_menu_id];
			echo '<b>'.$label.'</b>';
			echo '<span>'.\common::Link('Admin/Menu/Menus',$langmessage['rename'],'cmd=RenameMenuPrompt&id='.$this->curr_menu_id,'data-cmd="gpabox"').'</span>';
			$title_attr = sprintf($langmessage['generic_delete_confirm'],'&quot;'.$label.'&quot;');
			echo '<span>'.\common::Link('Admin/Menu/Menus',$langmessage['delete'],'cmd=MenuRemove&id='.$this->curr_menu_id,array('data-cmd'=>'cnreq','class'=>'gpconfirm','title'=>$title_attr)).'</span>';

			echo '</div>';
		}


		echo '</div>';

		echo '<div class="gpclear"></div>';


	}

	public function GetAvailMenus($get_type='menu'){

		$result = array();
		foreach($this->avail_menus as $menu_id => $menu_label){

			$menu_type = 'menu';
			if( isset($this->list_displays[$menu_id]) ){
				$menu_type = 'display';
			}

			if( $menu_type == $get_type ){
				$result[$menu_id] = $menu_label;
			}
		}
		return $result;
	}


	/**
	 * Send updated page manager content via ajax
	 * we're replacing more than just the content
	 *
	 */
	public function MenuJsonResponse($replace_id, $content){
		global $page;

		$page->ajaxReplace[] = array('gp_menu_prep','','');
		$page->ajaxReplace[] = array('inner',$replace_id,$content);
		$page->ajaxReplace[] = array('gp_menu_refresh','','');

		ob_start();
		\gpOutput::GetMenu();
		$content = ob_get_clean();
		$page->ajaxReplace[] = array('inner','#admin_menu_wrap',$content);
	}



	public function OutputMenu(){
		global $langmessage, $gp_titles, $gpLayouts, $config;
		$menu_adjustments_made = false;

		if( $this->curr_menu_array === false ){
			msg($langmessage['OOPS'].' (Current menu not set)');
			return;
		}

		//get array of titles and levels
		$menu_keys = array();
		$menu_values = array();
		foreach($this->curr_menu_array as $key => $info){
			if( !isset($info['level']) ){
				break;
			}

			//remove deleted titles
			if( !isset($gp_titles[$key]) && !isset($info['url']) ){
				unset($this->curr_menu_array[$key]);
				$menu_adjustments_made = true;
				continue;
			}


			$menu_keys[] = $key;
			$menu_values[] = $info;
		}

		//if the menu is empty (because all the files in it were deleted elsewhere), recreate it with the home page
		if( count($menu_values) == 0 ){
			$this->curr_menu_array = \gp\admin\Menu\Tools::AltMenu_New();
			$menu_keys[] = key($this->curr_menu_array);
			$menu_values[] = current($this->curr_menu_array);
			$menu_adjustments_made = true;
		}


		$prev_layout = false;
		$curr_key = 0;

		$curr_level = $menu_values[$curr_key]['level'];
		$prev_level = 0;


		//for sites that don't start with level 0
		if( $curr_level > $prev_level ){
			$piece = '<li><div>&nbsp;</div><ul>';
			while( $curr_level > $prev_level ){
				echo $piece;
				$prev_level++;
			}
		}



		do{

			echo "\n";

			$class = '';
			$menu_value = $menu_values[$curr_key];
			$menu_key = $menu_keys[$curr_key];
			$curr_level = $menu_value['level'];


			$next_level = 0;
			if( isset($menu_values[$curr_key+1]) ){
				$next_level = $menu_values[$curr_key+1]['level'];
			}

			if( $next_level > $curr_level ){
				$class = 'haschildren';
			}
			if( isset($this->hidden_levels[$menu_key]) ){
				$class .= ' hidechildren';
			}
			if( $curr_level >= $this->max_level_index){
				$class .= ' no-nest';
			}

			$class = \gp\admin\Menu\Tools::VisibilityClass($class, $menu_key);


			//layout
			$style = '';
			if( $this->is_main_menu ){
				if( isset($gp_titles[$menu_key]['gpLayout'])
					&& isset($gpLayouts[$gp_titles[$menu_key]['gpLayout']]) ){
						$color = $gpLayouts[$gp_titles[$menu_key]['gpLayout']]['color'];
						$style = 'background-color:'.$color.';';
				}elseif( $curr_level == 0 ){
					//$color = $gpLayouts[$config['gpLayout']]['color'];
					//$style = 'border-color:'.$color;
				}
			}


			echo '<li class="'.$class.'" style="'.$style.'">';

			if( $curr_level == 0 ){
				$prev_layout = false;
			}

			$this->ShowLevel($menu_key,$menu_value,$prev_layout);

			if( !empty($gp_titles[$menu_key]['gpLayout']) ){
				$prev_layout = $gp_titles[$menu_key]['gpLayout'];
			}

			if( $next_level > $curr_level ){

				$piece = '<ul>';
				while( $next_level > $curr_level ){
					echo $piece;
					$curr_level++;
					$piece = '<li class="missing_title"><div>'
							.'<a href="#" class="gp_label" data-cmd="menu_info">'
							.$langmessage['page_deleted']
							.'</a>'
							.'<p><b>'.$langmessage['page_deleted'].'</b></p>'
							.'</div><ul>';
				}

			}elseif( $next_level < $curr_level ){

				while( $next_level < $curr_level ){
					echo '</li></ul>';
					$curr_level--;
				}
				echo '</li>';
			}elseif( $next_level == $curr_level ){
				echo '</li>';
			}

			$prev_level = $curr_level;

		}while( ++$curr_key && ($curr_key < count($menu_keys) ) );

		if( $menu_adjustments_made ){
			$this->SaveMenu(false);
		}
	}


	public function ShowLevel($menu_key,$menu_value,$prev_layout){
		global $gp_titles, $gpLayouts;

		$layout			= \gp\admin\Menu\Tools::CurrentLayout($menu_key);
		$layout_info	= $gpLayouts[$layout];

		echo '<div id="gp_menu_key_'.$menu_key.'">';

		$style = '';
		$class = 'expand_img';
		if( !empty($gp_titles[$menu_key]['gpLayout']) ){
			$style = 'style="background-color:'.$layout_info['color'].';"';
			$class .= ' haslayout';
		}

		echo '<a href="#" class="'.$class.'" data-cmd="expand_img" '.$style.'></a>';

		if( isset($gp_titles[$menu_key]) ){
			$this->ShowLevel_Title($menu_key,$menu_value,$layout_info);
		}elseif( isset($menu_value['url']) ){
			$this->ShowLevel_External($menu_key,$menu_value);
		}
		echo '</div>';
	}


	/**
	 * Show a menu entry if it's an external link
	 *
	 */
	public function ShowLevel_External($menu_key,$menu_value){

		$data = array(
				'key'		=>	$menu_key
				,'url'		=>	$menu_value['url']
				,'title'	=>	$menu_value['url']
				,'level'	=>	$menu_value['level']
				);

		if( strlen($data['title']) > 30 ){
			$data['title'] = substr($data['title'],0,30).'...';
		}

		\gp\admin\Menu\Tools::MenuLink($data,'external');
		echo \common::LabelSpecialChars($menu_value['label']);
		echo '</a>';
	}

	public function MenuSkeletonExtern(){
		global $langmessage;

		echo '<b>'.$langmessage['Target URL'].'</b>';
		echo '<span>';
		$img = '<img alt="" />';
		echo '<a href="[url]" target="_blank">[title]</a>';
		echo '</span>';

		echo '<b>'.$langmessage['options'].'</b>';
		echo '<span>';

		$img = '<span class="menu_icon page_edit_icon"></span>';
		echo $this->Link('Admin/Menu',$img.$langmessage['edit'],'cmd=edit_external&key=[key]',array('title'=>$langmessage['edit'],'data-cmd'=>'gpabox'));

		$img = '<span class="menu_icon cut_list_icon"></span>';
		echo $this->Link('Admin/Menu',$img.$langmessage['rm_from_menu'],'cmd=hide&index=[key]',array('title'=>$langmessage['rm_from_menu'],'data-cmd'=>'postlink','class'=>'gpconfirm'));

		echo '</span>';

		$this->InsertLinks();
	}


	/**
	 * Show a menu entry if it's an internal page
	 *
	 */
	public function ShowLevel_Title($menu_key,$menu_value,$layout_info){
		global $langmessage, $gp_titles;


		$title						= \common::IndexToTitle($menu_key);
		$label						= \common::GetLabel($title);
		$isSpecialLink				= \common::SpecialOrAdmin($title);



		//get the data for this title
		$data = array(
					'key'			=>	$menu_key
					,'url'			=>	\common::GetUrl($title)
					,'level'		=>	$menu_value['level']
					,'title'		=>	$title
					,'special'		=>	$isSpecialLink
					,'has_layout'	=>	!empty($gp_titles[$menu_key]['gpLayout'])
					,'layout_color'	=>	$layout_info['color']
					,'layout_label'	=>	$layout_info['label']
					,'types'		=>	$gp_titles[$menu_key]['type']
					,'opts'			=> ''
					);


		if( $isSpecialLink === false ){
			$file = \gpFiles::PageFile($title);
			$stats = @stat($file);
			if( $stats ){
				$data += array(
						'size'		=>	\admin_tools::FormatBytes($stats['size'])
						,'mtime'	=>	\common::date($langmessage['strftime_datetime'],$stats['mtime'])
						);
			}
		}

		ob_start();
		\gpPlugin::Action('MenuPageOptions',array($title,$menu_key,$menu_value,$layout_info));
		$menu_options = ob_get_clean();
		if( $menu_options ){
			$data['opts'] = $menu_options;
		}

		\gp\admin\Menu\Tools::MenuLink($data);
		echo \common::LabelSpecialChars($label);
		echo '</a>';
	}


	/**
	 * Output html for the menu editing options displayed for selected titles
	 *
	 */
	public function MenuSkeleton(){
		global $langmessage;

		//page options
		echo '<b>'.$langmessage['page_options'].'</b>';

		echo '<span>';

		$img	= '<span class="menu_icon icon_page"></span>';
		echo '<a href="[url]" class="view_edit_link not_multiple">'.$img.htmlspecialchars($langmessage['view/edit_page']).'</a>';

		$img	= '<span class="menu_icon page_edit_icon"></span>';
		$attrs	= array('title'=>$langmessage['rename/details'],'data-cmd'=>'gpajax','class'=>'not_multiple');
		echo $this->Link('Admin/Menu',$img.$langmessage['rename/details'],'cmd=renameform&index=[key]',$attrs);


		$img	= '<i class="fa fa-eye menu_icon"></i>';
		$q		= 'cmd=ToggleVisibility&index=[key]';
		$label	= $langmessage['Visibility'].': '.$langmessage['Private'];
		$attrs	= array('title'=>$label,'data-cmd'=>'gpajax','class'=>'vis_private');
		echo $this->Link('Admin/Menu',$img.$label,$q,$attrs);

		$label	= $langmessage['Visibility'].': '.$langmessage['Public'];
		$attrs	= array('title'=>$label,'data-cmd'=>'gpajax','class'=>'vis_public not_multiple');
		$q		.= '&visibility=private';
		echo $this->Link('Admin/Menu',$img.$label,$q,$attrs);


		echo '<a href="[url]?cmd=ViewHistory" class="view_edit_link not_multiple not_special" data-cmd="gpabox"><i class="fa fa-history menu_icon"></i>'.htmlspecialchars($langmessage['Revision History']).'</a>';


		$img	= '<span class="menu_icon copy_icon"></span>';
		$attrs	= array('title'=>$langmessage['Copy'],'data-cmd'=>'gpabox','class'=>'not_multiple');
		echo $this->Link('Admin/Menu',$img.$langmessage['Copy'],'cmd=copypage&index=[key]',$attrs);


		if( \admin_tools::HasPermission('Admin_User') ){
			$img	= '<span class="menu_icon icon_user"></span>';
			$attrs	= array('title'=>$langmessage['permissions'],'data-cmd'=>'gpabox');
			echo $this->Link('Admin_Users',$img.$langmessage['permissions'],'cmd=file_permissions&index=[key]',$attrs);
		}

		$img	= '<span class="menu_icon cut_list_icon"></span>';
		$attrs	= array('title'=>$langmessage['rm_from_menu'],'data-cmd'=>'postlink','class'=>'gpconfirm');
		echo $this->Link('Admin/Menu',$img.$langmessage['rm_from_menu'],'cmd=hide&index=[key]',$attrs);

		$img	= '<span class="menu_icon bin_icon"></span>';
		$attrs	= array('title'=>$langmessage['delete_page'],'data-cmd'=>'postlink','class'=>'gpconfirm not_special');
		echo $this->Link('Admin/Menu',$img.$langmessage['delete'],'cmd=trash&index=[key]',$attrs);

		echo '[opts]'; //replaced with the contents of \gpPlugin::Action('MenuPageOptions',array($title,$menu_key,$menu_value,$layout_info));

		echo '</span>';


		//layout
		if( $this->is_main_menu ){
			echo '<div class="not_multiple">';
			echo '<b>'.$langmessage['layout'].'</b>';
			echo '<span>';

			//has_layout
			$img = '<span class="layout_icon"></span>';
			echo $this->Link('Admin/Menu',$img.'[layout_label]','cmd=layout&index=[key]',' title="'.$langmessage['layout'].'" data-cmd="gpabox" class="has_layout"');

			$img = '<span class="menu_icon undo_icon"></span>';
			echo $this->Link('Admin/Menu',$img.$langmessage['restore'],'cmd=restorelayout&index=[key]',array('data-cmd'=>'postlink','title'=>$langmessage['restore'],'class'=>'has_layout'),'restore');

			//no_layout
			$img = '<span class="layout_icon"></span>';
			echo $this->Link('Admin/Menu',$img.'[layout_label]','cmd=layout&index=[key]',' title="'.$langmessage['layout'].'" data-cmd="gpabox" class="no_layout"');
			echo '</span>';
			echo '</div>';
		}

		$this->InsertLinks();


		//file stats
		echo '<div>';
		echo '<b>'.$langmessage['Page Info'].'</b>';
		echo '<span>';
		echo '<a class="not_multiple">'.$langmessage['Slug/URL'].': [title]</a>';
		echo '<a class="not_multiple">'.$langmessage['Content Type'].': [types]</a>';
		echo '<a class="not_special only_multiple">'.sprintf($langmessage['%s Pages'],'[files]').'</a>';
		echo '<a class="not_special">'.$langmessage['File Size'].': [size]</a>';
		echo '<a class="not_special not_multiple">'.$langmessage['Modified'].': [mtime]</a>';
		echo '<a class="not_multiple">Data Index: [key]</a>';
		echo '</span>';
		echo '</div>';

	}


	/**
	 * Output Insert links displayed with page options
	 *
	 */
	public function InsertLinks(){
		global $langmessage;

		echo '<div class="not_multiple">';
		echo '<b>'.$langmessage['insert_into_menu'].'</b>';
		echo '<span>';

		$img = '<span class="menu_icon insert_before_icon"></span>';
		$query = 'cmd=insert_before&insert_where=[key]';
		echo $this->Link('Admin/Menu',$img.$langmessage['insert_before'],$query,array('title'=>$langmessage['insert_before'],'data-cmd'=>'gpabox'));


		$img = '<span class="menu_icon insert_after_icon"></span>';
		$query = 'cmd=insert_after&insert_where=[key]';
		echo $this->Link('Admin/Menu',$img.$langmessage['insert_after'],$query,array('title'=>$langmessage['insert_after'],'data-cmd'=>'gpabox'));


		$img = '<span class="menu_icon insert_after_icon"></span>';
		$query = 'cmd=insert_child&insert_where=[key]';
		echo $this->Link('Admin/Menu',$img.$langmessage['insert_child'],$query,array('title'=>$langmessage['insert_child'],'data-cmd'=>'gpabox','class'=>'insert_child'));
		echo '</span>';
		echo '</div>';
	}



	/**
	 * List section types
	 *
	 */
	public function TitleTypes($title_index){
		global $gp_titles;

		$types		= explode(',',$gp_titles[$title_index]['type']);
		$types		= array_filter($types);
		$types		= array_unique($types);

		foreach($types as $i => $type){
			if( isset($this->section_types[$type]) && isset($this->section_types[$type]['label']) ){
				$types[$i] = $this->section_types[$type]['label'];
			}
		}

		echo implode(', ',$types);
	}


	/**
	 * Get a list of pages that are not in the current menu array
	 * @return array
	 */
	protected function GetAvail_Current(){
		global $gp_index;

		if( $this->is_main_menu ){
			return \gp\admin\Menu\Tools::GetAvailable();
		}

		foreach( $gp_index as $title => $index ){
			if( !isset($this->curr_menu_array[$index]) ){
				$avail[$index] = $title;
			}
		}
		return $avail;
	}


	/**
	 * Save changes to the current menu array after a drag event occurs
	 * @return bool
	 */
	public function SaveDrag(){
		global $langmessage;

		$this->CacheSettings();
		if( $this->curr_menu_array === false ){
			msg($langmessage['OOPS'].'(1)');
			return false;
		}

		$key = $_POST['drag_key'];
		if( !isset($this->curr_menu_array[$key]) ){
			msg($langmessage['OOPS'].' (Unknown menu key)');
			return false;
		}


		$moved = $this->RmMoved($key);
		if( !$moved ){
			msg($langmessage['OOPS'].'(3)');
			return false;
		}


		// if prev (sibling) set
		$inserted = true;
		if( !empty($_POST['prev']) ){

			$inserted = $this->MenuInsert_After( $moved, $_POST['prev']);

		// if parent is set
		}elseif( !empty($_POST['parent']) ){

			$inserted = $this->MenuInsert_Child( $moved, $_POST['parent']);

		// if no siblings, no parent then it's the root
		}else{
			$inserted = $this->MenuInsert_Before( $moved, false);

		}

		if( !$inserted ){
			$this->RestoreSettings();
			msg($langmessage['OOPS'].'(4)');
			return;
		}

		if( !$this->SaveMenu(false) ){
			$this->RestoreSettings();
			\common::AjaxWarning();
			return false;
		}

	}


	/**
	 * Get portion of menu that was moved
	 */
	public function RmMoved($key){
		if( !isset($this->curr_menu_array[$key]) ){
			return false;
		}

		$old_level = false;
		$moved = array();

		foreach($this->curr_menu_array as $menu_key => $info){

			if( !isset($info['level']) ){
				break;
			}
			$level = $info['level'];

			if( $old_level === false ){

				if( $menu_key != $key ){
					continue;
				}

				$old_level = $level;
				$moved[$menu_key] = $info;
				unset($this->curr_menu_array[$menu_key]);
				continue;
			}

			if( $level <= $old_level ){
				break;
			}

			$moved[$menu_key] = $info;
			unset($this->curr_menu_array[$menu_key]);
		}
		return $moved;
	}



	/**
	 * Move To Trash
	 * Hide special pages
	 *
	 */
	public function MoveToTrash($cmd){
		global $gp_titles, $gp_index, $langmessage, $gp_menu, $config, $dataDir;

		includeFile('admin/admin_trash.php');
		$this->CacheSettings();

		$_POST			+= array('index'=>'');
		$indexes		= explode(',',$_POST['index']);
		$trash_data		= array();
		$delete_files	= array();


		foreach($indexes as $index){

			$title	= \common::IndexToTitle($index);

			// Create file in trash
			if( $title ){
				if( !\admin_trash::MoveToTrash_File($title,$index,$trash_data) ){
					msg($langmessage['OOPS'].' (Not Moved)');
					$this->RestoreSettings();
					return false;
				}
			}


			// Remove from menu
			if( isset($gp_menu[$index]) ){

				if( count($gp_menu) == 1 ){
					continue;
				}

				if( !$this->RmFromMenu($index,false) ){
					msg($langmessage['OOPS']);
					$this->RestoreSettings();
					return false;
				}
			}

			unset($gp_titles[$index]);
			unset($gp_index[$title]);
		}


		\gp\admin\Menu\Tools::ResetHomepage();


		if( !\admin_tools::SaveAllConfig() ){
			$this->RestoreSettings();
			return false;
		}

		$link = \common::GetUrl('Admin_Trash');
		msg(sprintf($langmessage['MOVED_TO_TRASH'],$link));


		\gpPlugin::Action('MenuPageTrashed',array($indexes));

		return true;
	}


	/**
	 * Remove key from curr_menu_array
	 * Adjust children levels if necessary
	 *
	 */
	protected function RmFromMenu($search_key,$curr_menu=true){
		global $gp_menu;

		if( $curr_menu ){
			$keys = array_keys($this->curr_menu_array);
			$values = array_values($this->curr_menu_array);
		}else{
			$keys = array_keys($gp_menu);
			$values = array_values($gp_menu);
		}

		$insert_key = array_search($search_key,$keys);
		if( ($insert_key === null) || ($insert_key === false) ){
			return false;
		}

		$curr_info = $values[$insert_key];
		$curr_level = $curr_info['level'];

		unset($keys[$insert_key]);
		$keys = array_values($keys);

		unset($values[$insert_key]);
		$values = array_values($values);


		//adjust levels of children
		$prev_level = -1;
		if( isset($values[$insert_key-1]) ){
			$prev_level = $values[$insert_key-1]['level'];
		}
		$moved_one = true;
		do{
			$moved_one = false;
			if( isset($values[$insert_key]) ){
				$curr_level = $values[$insert_key]['level'];
				if( ($prev_level+1) < $curr_level ){
					$values[$insert_key]['level']--;
					$prev_level = $values[$insert_key]['level'];
					$moved_one = true;
					$insert_key++;
				}
			}
		}while($moved_one);

		//shouldn't happen
		if( count($keys) == 0 ){
			return false;
		}

		//rebuild
		if( $curr_menu ){
			$this->curr_menu_array = array_combine($keys, $values);
		}else{
			$gp_menu = array_combine($keys, $values);
		}

		return true;
	}



	/**
	 * Rename
	 *
	 */
	public function RenameForm(){
		global $langmessage, $gp_index;

		includeFile('tool/Page_Rename.php');

		//prepare variables
		$title =& $_REQUEST['index'];
		$action = $this->GetUrl('Admin/Menu');
		\gp_rename::RenameForm( $_REQUEST['index'], $action );
	}

	public function RenameFile(){
		global $langmessage, $gp_index;

		includeFile('tool/Page_Rename.php');


		//prepare variables
		$title =& $_REQUEST['title'];
		if( !isset($gp_index[$title]) ){
			msg($langmessage['OOPS'].' (R0)');
			return false;
		}

		\gp_rename::RenameFile($title);
	}


	/**
	 * Toggle Page Visibility
	 *
	 */
	public function ToggleVisibility(){
		$_REQUEST += array('index'=>'','visibility'=>'');
		\gp\tool\Visibility::Toggle($_REQUEST['index'], $_REQUEST['visibility']);
	}


	/**
	 * Remove from the menu
	 *
	 */
	public function Hide(){
		global $langmessage;

		if( $this->curr_menu_array === false ){
			msg($langmessage['OOPS'].'(1)');
			return false;
		}

		$this->CacheSettings();

		$_POST		+= array('index'=>'');
		$indexes 	= explode(',',$_POST['index']);

		foreach($indexes as $index ){

			if( count($this->curr_menu_array) == 1 ){
				break;
			}

			if( !isset($this->curr_menu_array[$index]) ){
				msg($langmessage['OOPS'].'(3)');
				return false;
			}

			if( !$this->RmFromMenu($index) ){
				msg($langmessage['OOPS'].'(4)');
				$this->RestoreSettings();
				return false;
			}
		}

		if( $this->SaveMenu(false) ){
			return true;
		}

		msg($langmessage['OOPS'].'(5)');
		$this->RestoreSettings();
		return false;
	}

	/**
	 * Display a user form for adding a new page that won't be immediately added to a menu
	 *
	 */
	public function AddHidden(){
		global $langmessage, $page, $gp_index;

		includeFile('tool/editing_page.php');
		$_REQUEST += array('title'=>'');
		$_REQUEST['gpx_content'] = 'gpabox';

		//reusable format
		ob_start();
		echo '<p>';
		echo '<button type="submit" name="cmd" value="%s" class="gpsubmit gpvalidate" data-cmd="gppost">%s</button>';
		echo '<button class="admin_box_close gpcancel">'.$langmessage['cancel'].'</button>';
		echo '</p>';
		echo '</td></tr>';
		echo '</tbody>';
		$format_bottom = ob_get_clean();




		echo '<div class="inline_box">';

		echo '<div class="layout_links" style="float:right">';
		echo '<a href="#gp_new_copy" data-cmd="tabs" class="selected">'. $langmessage['Copy'] .'</a>';
		echo '<a href="#gp_new_type" data-cmd="tabs">'. $langmessage['Content Type'] .'</a>';
		echo '</div>';


		echo '<h3>'.$langmessage['new_file'].'</h3>';


		echo '<form action="'.$this->GetUrl('Admin/Menu').'" method="post">';
		if( isset($_GET['redir']) ){
			echo '<input type="hidden" name="redir" value="redir" />';
		}


		echo '<table class="bordered full_width">';
		echo '<tr><th colspan="2">'.$langmessage['options'].'</th></tr>';

		//title
		echo '<tr><td>';
		echo $langmessage['label'];
		echo '</td><td>';
		echo '<input type="text" name="title" maxlength="100" size="50" value="'.htmlspecialchars($_REQUEST['title']).'" class="gpinput full_width" required/>';
		echo '</td></tr>';

		//copy
		echo '<tbody id="gp_new_copy">';
		echo '<tr><td>';
		echo $langmessage['Copy'];
		echo '</td><td>';
		\gp\admin\Menu\Tools::ScrollList($gp_index);
		echo sprintf($format_bottom,'CopyPage',$langmessage['create_new_file']);


		//content type
		echo '<tr id="gp_new_type" style="display:none"><td>';
		echo str_replace(' ','&nbsp;',$langmessage['Content Type']);
		echo '</td><td>';
		echo '<div id="new_section_links">';
		\editing_page::NewSections(true);
		echo '</div>';

		echo sprintf($format_bottom,'new_hidden',$langmessage['create_new_file']);
		echo '</form>';
		echo '</div>';
	}


	/**
	 * Display the dialog for inserting pages into a menu
	 *
	 */
	public function InsertDialog($cmd){
		global $langmessage, $page, $gp_index;

		includeFile('admin/admin_trash.php');

		//create format of each tab
		ob_start();
		echo '<div id="%s" class="%s">';
		echo '<form action="'.\common::GetUrl('Admin/Menu').'" method="post">';
		echo '<input type="hidden" name="insert_where" value="'.htmlspecialchars($_GET['insert_where']).'" />';
		echo '<input type="hidden" name="insert_how" value="'.htmlspecialchars($cmd).'" />';
		echo '<table class="bordered full_width">';
		echo '<thead><tr><th>&nbsp;</th></tr></thead>';
		echo '</table>';
		$format_top = ob_get_clean();

		ob_start();
		echo '<p>';
		echo '<button type="submit" name="cmd" value="%s" class="gpsubmit" data-cmd="gppost">%s</button>';
		echo '<button class="admin_box_close gpcancel">'.$langmessage['cancel'].'</button>';
		echo '</p>';
		echo '</form>';
		echo '</div>';
		$format_bottom = ob_get_clean();



		echo '<div class="inline_box">';

			//tabs
			echo '<div class="layout_links">';
			echo ' <a href="#gp_Insert_Copy" data-cmd="tabs" class="selected">'. $langmessage['Copy'] .'</a>';
			echo ' <a href="#gp_Insert_New" data-cmd="tabs">'. $langmessage['new_file'] .'</a>';
			echo ' <a href="#gp_Insert_Hidden" data-cmd="tabs">'. $langmessage['Available'] .'</a>';
			echo ' <a href="#gp_Insert_External" data-cmd="tabs">'. $langmessage['External Link'] .'</a>';
			echo ' <a href="#gp_Insert_Deleted" data-cmd="tabs">'. $langmessage['trash'] .'</a>';
			echo '</div>';


			// Copy
			echo sprintf($format_top,'gp_Insert_Copy','');
			echo '<table class="bordered full_width">';
			echo '<tr><td>';
			echo $langmessage['label'];
			echo '</td><td>';
			echo '<input type="text" name="title" maxlength="100" size="50" value="" class="gpinput full_width" required/>';
			echo '</td></tr>';
			echo '<tr><td>';
			echo $langmessage['Copy'];
			echo '</td><td>';
			\gp\admin\Menu\Tools::ScrollList($gp_index);
			echo '</td></tr>';
			echo '</table>';
			echo sprintf($format_bottom,'CopyPage',$langmessage['Copy']);


			// Insert New
			echo sprintf($format_top,'gp_Insert_New','nodisplay');
			echo '<table class="bordered full_width">';
			echo '<tr><td>';
			echo $langmessage['label'];
			echo '</td><td>';
			echo '<input type="text" name="title" maxlength="100" value="" size="50" class="gpinput full_width" required />';
			echo '</td></tr>';

			echo '<tr><td>';
			echo $langmessage['Content Type'];
			echo '</td><td>';
			includeFile('tool/editing_page.php');
			echo '<div id="new_section_links">';
			\editing_page::NewSections(true);
			echo '</div>';
			echo '</td></tr>';
			echo '</table>';
			echo sprintf($format_bottom,'new_file',$langmessage['create_new_file']);


			// Insert Hidden
			$avail = $this->GetAvail_Current();

			if( $avail ){
				echo sprintf($format_top,'gp_Insert_Hidden','nodisplay');
				$avail = array_flip($avail);
				\gp\admin\Menu\Tools::ScrollList($avail,'keys[]','checkbox',true);
				echo sprintf($format_bottom,'insert_from_hidden',$langmessage['insert_into_menu']);
			}



			// Insert Deleted / Restore from trash
			$trashtitles = \admin_trash::TrashFiles();
			if( $trashtitles ){
				echo sprintf($format_top,'gp_Insert_Deleted','nodisplay');

				echo '<div class="gpui-scrolllist">';
				echo '<input type="text" name="search" value="" class="gpsearch" placeholder="'.$langmessage['Search'].'" autocomplete="off" />';
				foreach($trashtitles as $title => $info){
					echo '<label>';
					echo '<input type="checkbox" name="titles[]" value="'.htmlspecialchars($title).'" />';
					echo '<span>';
					echo $info['label'];
					echo '<span class="slug">';
					if( isset($info['title']) ){
						echo '/'.$info['title'];
					}else{
						echo '/'.$title;
					}
					echo '</span>';
					echo '</span>';
					echo '</label>';
				}
				echo '</div>';
				echo sprintf($format_bottom,'restore',$langmessage['restore_from_trash']);
			}


			//Insert External
			echo '<div id="gp_Insert_External" class="nodisplay">';
			$args['insert_how']		= $cmd;
			$args['insert_where']	= $_GET['insert_where'];
			$this->ExternalForm('new_external',$langmessage['insert_into_menu'],$args);
			echo '</div>';


		echo '</div>';

	}

	/**
	 * Insert pages into the current menu from existing pages that aren't in the menu
	 *
	 */
	public function InsertFromHidden(){
		global $langmessage, $gp_index;

		if( $this->curr_menu_array === false ){
			msg($langmessage['OOPS'].' (Menu not set)');
			return false;
		}

		$this->CacheSettings();

		//get list of titles from submitted indexes
		$titles = array();
		if( isset($_POST['keys']) ){
			foreach($_POST['keys'] as $index){
				if( $title = \common::IndexToTitle($index) ){
					$titles[$index]['level'] = 0;
				}
			}
		}

		if( count($titles) == 0 ){
			msg($langmessage['OOPS'].' (Nothing selected)');
			$this->RestoreSettings();
			return false;
		}

		if( !$this->SaveNew($titles) ){
			$this->RestoreSettings();
			return false;
		}

	}


	/**
	 * Add titles to the current menu from the trash
	 *
	 */
	public function RestoreFromTrash(){
		global $langmessage, $gp_index;


		if( $this->curr_menu_array === false ){
			msg($langmessage['OOPS']);
			return false;
		}

		if( !isset($_POST['titles']) ){
			msg($langmessage['OOPS'].' (Nothing Selected)');
			return false;
		}

		$this->CacheSettings();
		includeFile('admin/admin_trash.php');

		$titles_lower	= array_change_key_case($gp_index,CASE_LOWER);
		$titles			= array();
		$menu			= \admin_trash::RestoreTitles($_POST['titles']);


		if( !$menu ){
			msg($langmessage['OOPS']);
			$this->RestoreSettings();
			return false;
		}


		if( !$this->SaveNew($menu) ){
			$this->RestoreSettings();
			return false;
		}

		\admin_trash::ModTrashData(null,$titles);
	}


	/**
	 * Create a new hidden
	 *
	 */
	public function NewHiddenFile(){
		global $langmessage;

		$this->CacheSettings();

		$new_index = \gp\admin\Menu\Tools::CreateNew();
		if( $new_index === false ){
			return false;
		}


		if( !\admin_tools::SavePagesPHP() ){
			msg($langmessage['OOPS']);
			$this->RestoreSettings();
			return false;
		}

		$this->HiddenSaved($new_index);

		return $new_index;
	}


	/**
	 * Message or redirect when file is saved
	 *
	 */
	public function HiddenSaved($new_index){
		global $langmessage, $page;

		$this->search_page = 0; //take user back to first page where the new page will be displayed

		if( isset($_REQUEST['redir']) ){
			$title	= \common::IndexToTitle($new_index);
			$url	= \common::AbsoluteUrl($title,'',true,false);
			msg(sprintf($langmessage['will_redirect'],\common::Link_Page($title)));
			$page->ajaxReplace[] = array('location',$url,15000);
		}else{
			msg($langmessage['SAVED']);
		}


	}


	public function NewFile(){
		global $langmessage;
		$this->CacheSettings();


		if( $this->curr_menu_array === false ){
			msg($langmessage['OOPS'].'(0)');
			return false;
		}

		if( !isset($this->curr_menu_array[$_POST['insert_where']]) ){
			msg($langmessage['OOPS'].'(1)');
			return false;
		}


		$new_index = \gp\admin\Menu\Tools::CreateNew();
		if( $new_index === false ){
			return false;
		}

		$insert = array();
		$insert[$new_index] = array();

		if( !$this->SaveNew($insert) ){
			$this->RestoreSettings();
			return false;
		}
	}


	/**
	 * Save pages
	 *
	 * @param array $titles
	 * @return bool
	 */
	protected function SaveNew($titles){
		global $langmessage;

		//menu modification
		if( isset($_POST['insert_where']) && isset($_POST['insert_how']) ){

			if( !$this->MenuInsert($titles,$_POST['insert_where'],$_POST['insert_how']) ){
				msg($langmessage['OOPS'].' (Insert Failed)');
				return false;
			}

			if( !$this->SaveMenu(true) ){
				msg($langmessage['OOPS'].' (Menu Not Saved)');
				return false;
			}

			return true;
		}


		if( !\admin_tools::SavePagesPHP() ){
			msg($langmessage['OOPS'].' (Page index not saved)');
			return false;
		}

		return true;
	}


	/**
	 * Insert titles into the current menu if needed
	 *
	 */
	public function MenuInsert($titles,$neighbor,$insert_how){
		switch($insert_how){
			case 'insert_before':
			return $this->MenuInsert_Before($titles,$neighbor);

			case 'insert_after':
			return $this->MenuInsert_After($titles,$neighbor);

			case 'insert_child':
			return $this->MenuInsert_After($titles,$neighbor,1);
		}

		return false;
	}


	/**
	 * Insert titles into menu
	 *
	 */
	protected function MenuInsert_Before($titles,$sibling){

		$old_level = \gp\admin\Menu\Tools::GetRootLevel($titles);

		//root install
		if( $sibling === false ){
			$level_adjustment = 0 - $old_level;
			$titles = $this->AdjustMovedLevel($titles,$level_adjustment);
			$this->curr_menu_array = $titles + $this->curr_menu_array;
			return true;
		}


		//before sibling
		if( !isset($this->curr_menu_array[$sibling]) || !isset($this->curr_menu_array[$sibling]['level']) ){
			return false;
		}

		$sibling_level = $this->curr_menu_array[$sibling]['level'];
		$level_adjustment = $sibling_level - $old_level;
		$titles = $this->AdjustMovedLevel($titles,$level_adjustment);

		$new_menu = array();
		foreach($this->curr_menu_array as $menu_key => $menu_info ){

			if( $menu_key == $sibling ){
				foreach($titles as $titles_key => $titles_info){
					$new_menu[$titles_key] = $titles_info;
				}
			}
			$new_menu[$menu_key] = $menu_info;
		}
		$this->curr_menu_array = $new_menu;
		return true;
	}

	/*
	 * Insert $titles into $menu as siblings of $sibling
	 * Place
	 *
	 */
	protected function MenuInsert_After($titles,$sibling,$level_adjustment=0){

		if( !isset($this->curr_menu_array[$sibling]) || !isset($this->curr_menu_array[$sibling]['level']) ){
			return false;
		}

		$sibling_level = $this->curr_menu_array[$sibling]['level'];

		//level adjustment
		$old_level			= \gp\admin\Menu\Tools::GetRootLevel($titles);
		$level_adjustment	+= $sibling_level - $old_level;
		$titles				= $this->AdjustMovedLevel($titles,$level_adjustment);


		// rebuild menu
		//	insert $titles after sibling and it's children
		$new_menu = array();
		$found_sibling = false;
		foreach($this->curr_menu_array as $menu_key => $menu_info){

			$menu_level = 0;
			if( isset($menu_info['level']) ){
				$menu_level = $menu_info['level'];
			}

			if( $found_sibling && ($menu_level <= $sibling_level) ){
				foreach($titles as $titles_key => $titles_info){
					$new_menu[$titles_key] = $titles_info;
				}
				$found_sibling = false; //prevent multiple insertions
			}

			$new_menu[$menu_key] = $menu_info;

			if( $menu_key == $sibling ){
				$found_sibling = true;
			}
		}

		//if it's added to the end
		if( $found_sibling ){
			foreach($titles as $titles_key => $titles_info){
				$new_menu[$titles_key] = $titles_info;
			}
		}
		$this->curr_menu_array = $new_menu;

		return true;
	}

	/*
	 * Insert $titles into $menu as children of $parent
	 *
	 */
	protected function MenuInsert_Child($titles,$parent){

		if( !isset($this->curr_menu_array[$parent]) || !isset($this->curr_menu_array[$parent]['level']) ){
			return false;
		}

		$parent_level = $this->curr_menu_array[$parent]['level'];


		//level adjustment
		$old_level			= \gp\admin\Menu\Tools::GetRootLevel($titles);
		$level_adjustment	= $parent_level - $old_level + 1;
		$titles				= $this->AdjustMovedLevel($titles,$level_adjustment);

		//rebuild menu
		//	insert $titles after parent
		$new_menu = array();
		foreach($this->curr_menu_array as $menu_title => $menu_info){
			$new_menu[$menu_title] = $menu_info;

			if( $menu_title == $parent ){
				foreach($titles as $titles_title => $titles_info){
					$new_menu[$titles_title] = $titles_info;
				}
			}
		}

		$this->curr_menu_array = $new_menu;
		return true;
	}

	protected function AdjustMovedLevel($titles,$level_adjustment){

		foreach($titles as $title => $info){
			$level = 0;
			if( isset($info['level']) ){
				$level = $info['level'];
			}
			$titles[$title]['level'] = min($this->max_level_index,$level + $level_adjustment);
		}
		return $titles;
	}


	/*
	 * External Links
	 *
	 *
	 */
	public function ExternalForm($cmd,$submit,$args){
		global $langmessage;

		//these aren't all required for each usage of ExternalForm()
		$args += array(
					'url'=>'http://',
					'label'=>'',
					'title_attr'=>'',
					'insert_how'=>'',
					'insert_where'=>'',
					'key'=>''
					);


		echo '<form action="'.$this->GetUrl('Admin/Menu').'" method="post">';
		echo '<input type="hidden" name="insert_how" value="'.htmlspecialchars($args['insert_how']).'" />';
		echo '<input type="hidden" name="insert_where" value="'.htmlspecialchars($args['insert_where']).'" />';
		echo '<input type="hidden" name="key" value="'.htmlspecialchars($args['key']).'" />';

		echo '<table class="bordered full_width">';

		echo '<tr>';
			echo '<th>&nbsp;</th>';
			echo '<th>&nbsp;</th>';
			echo '</tr>';

		echo '<tr>';
			echo '<td>'.$langmessage['Target URL'].'</td>';
			echo '<td>';
			echo '<input type="text" name="url" value="'.$args['url'].'" class="gpinput"/>';
			echo '</td>';
			echo '</tr>';

		echo '<tr>';
			echo '<td>'.$langmessage['label'].'</td>';
			echo '<td>';
			echo '<input type="text" name="label" value="'.\common::LabelSpecialChars($args['label']).'" class="gpinput"/>';
			echo '</td>';
			echo '</tr>';

		echo '<tr>';
			echo '<td>'.$langmessage['title attribute'].'</td>';
			echo '<td>';
			echo '<input type="text" name="title_attr" value="'.$args['title_attr'].'" class="gpinput"/>';
			echo '</td>';
			echo '</tr>';

		echo '<tr>';
			echo '<td>'.$langmessage['New_Window'].'</td>';
			echo '<td>';
			if( isset($args['new_win']) ){
				echo '<input type="checkbox" name="new_win" value="new_win" checked="checked" />';
			}else{
				echo '<input type="checkbox" name="new_win" value="new_win" />';
			}
			echo '</td>';
			echo '</tr>';


		echo '</table>';

		echo '<p>';

		echo '<input type="hidden" name="cmd" value="'.htmlspecialchars($cmd).'" />';
		echo '<input type="submit" name="" value="'.$submit.'" class="gpsubmit" data-cmd="gppost"/> ';
		echo '<input type="submit" value="'.$langmessage['cancel'].'" class="admin_box_close gpcancel" /> ';
		echo '</p>';

		echo '</form>';
	}


	/**
	 * Edit an external link entry in the current menu
	 *
	 */
	public function EditExternal(){
		global $langmessage;

		$key =& $_GET['key'];
		if( !isset($this->curr_menu_array[$key]) ){
			msg($langmessage['OOPS'].' (Current menu not set)');
			return false;
		}

		$info = $this->curr_menu_array[$key];
		$info['key'] = $key;

		echo '<div class="inline_box">';

		echo '<h3>'.$langmessage['External Link'].'</h3>';

		$this->ExternalForm('save_external',$langmessage['save'],$info);

		echo '</div>';
	}


	/**
	 * Save changes to an external link entry in the current menu
	 *
	 */
	public function SaveExternal(){
		global $langmessage;

		$key =& $_POST['key'];
		if( !isset($this->curr_menu_array[$key]) ){
			msg($langmessage['OOPS'].' (Current menu not set)');
			return false;
		}
		$level = $this->curr_menu_array[$key]['level'];

		$array = $this->ExternalPost();
		if( !$array ){
			msg($langmessage['OOPS'].' (1)');
			return;
		}

		$this->CacheSettings();

		$array['level'] = $level;
		$this->curr_menu_array[$key] = $array;

		if( !$this->SaveMenu(false) ){
			msg($langmessage['OOPS'].' (Menu Not Saved)');
			$this->RestoreSettings();
			return false;
		}

	}


	/**
	 * Save a new external link in the current menu
	 *
	 */
	public function NewExternal(){
		global $langmessage;

		$this->CacheSettings();
		$array = $this->ExternalPost();

		if( !$array ){
			msg($langmessage['OOPS'].' (Invalid Request)');
			return;
		}

		$key			= $this->NewExternalKey();
		$insert[$key]	= $array;

		if( !$this->SaveNew($insert) ){
			$this->RestoreSettings();
			return false;
		}
	}


	/**
	 * Check the values of a post with external link values
	 *
	 */
	public function ExternalPost(){

		$array = array();
		if( empty($_POST['url']) || $_POST['url'] == 'http://' ){
			return false;
		}
		$array['url'] = htmlspecialchars($_POST['url']);

		if( !empty($_POST['label']) ){
			$array['label'] = \admin_tools::PostedLabel($_POST['label']);
		}
		if( !empty($_POST['title_attr']) ){
			$array['title_attr'] = htmlspecialchars($_POST['title_attr']);
		}
		if( isset($_POST['new_win']) && $_POST['new_win'] == 'new_win' ){
			$array['new_win'] = true;
		}
		return $array;
	}

	public function NewExternalKey(){

		$num_index = 0;
		do{
			$new_key = '_'.base_convert($num_index,10,36);
			$num_index++;
		}while( isset($this->curr_menu_array[$new_key]) );

		return $new_key;
	}

	/**
	 * Display a form for copying a page
	 *
	 */
	public function CopyForm(){
		global $langmessage, $gp_index, $page;


		$index = $_REQUEST['index'];
		$from_title = \common::IndexToTitle($index);

		if( !$from_title ){
			msg($langmessage['OOPS_TITLE']);
			return false;
		}

		$from_label = \common::GetLabel($from_title);
		$from_label = \common::LabelSpecialChars($from_label);

		echo '<div class="inline_box">';
		echo '<form method="post" action="'.\common::GetUrl('Admin/Menu').'">';
		if( isset($_REQUEST['redir']) ){
			echo '<input type="hidden" name="redir" value="redir"/> ';
		}
		echo '<input type="hidden" name="from_title" value="'.htmlspecialchars($from_title).'"/> ';
		echo '<table class="bordered full_width" id="gp_rename_table">';

		echo '<thead><tr><th colspan="2">';
		echo $langmessage['Copy'];
		echo '</th></tr></thead>';

		echo '<tr class="line_row"><td>';
		echo $langmessage['from'];
		echo '</td><td>';
		echo $from_label;
		echo '</td></tr>';

		echo '<tr><td>';
		echo $langmessage['to'];
		echo '</td><td>';
		echo '<input type="text" name="title" maxlength="100" size="50" value="'.$from_label.'" class="gpinput" />';
		echo '</td></tr>';

		echo '</table>';

		echo '<p>';
		echo '<input type="hidden" name="cmd" value="CopyPage"/> ';
		echo '<input type="submit" name="" value="'.$langmessage['continue'].'" class="gpsubmit" data-cmd="gppost"/>';
		echo '<input type="button" class="admin_box_close gpcancel" name="" value="'.$langmessage['cancel'].'" />';
		echo '</p>';

		echo '</form>';
		echo '</div>';
	}

	/**
	 * Perform a page copy
	 *
	 */
	public function CopyPage(){
		global $gp_index, $gp_titles, $page, $langmessage;

		$this->CacheSettings();

		if( !isset($_POST['from_title']) ){
			$this->AddHidden();
			msg($langmessage['OOPS'].' (Copy from not selected)');
			return false;
		}

		//existing page info
		$from_title = $_POST['from_title'];
		if( !isset($gp_index[$from_title]) ){
			msg($langmessage['OOPS_TITLE']);
			return false;
		}
		$from_index		= $gp_index[$from_title];
		$info			= $gp_titles[$from_index];


		//check the new title
		$title			= $_POST['title'];
		$title			= \admin_tools::CheckPostedNewPage($title,$message);
		if( $title === false ){
			msg($message);
			return false;
		}

		//get the existing content
		$from_file		= \gpFiles::PageFile($from_title);
		$contents		= file_get_contents($from_file);


		//add to $gp_index first!
		$index				= \common::NewFileIndex();
		$gp_index[$title]	= $index;
		$file = \gpFiles::PageFile($title);

		if( !\gpFiles::Save($file,$contents) ){
			msg($langmessage['OOPS'].' (File not saved)');
			return false;
		}

		//add to gp_titles
		$new_titles						= array();
		$new_titles[$index]['label']	= \admin_tools::PostedLabel($_POST['title']);
		$new_titles[$index]['type']		= $info['type'];
		$gp_titles						+= $new_titles;


		//add to menu
		$insert = array();
		$insert[$index] = array();

		if( !$this->SaveNew($insert) ){
			$this->RestoreSettings();
			return false;
		}


		$this->HiddenSaved($index);

		return true;
	}


	/**
	 * Display a form for selecting the homepage
	 *
	 */
	public function HomepageSelect(){
		global $langmessage;

		echo '<div class="inline_box">';
		echo '<form action="'.\common::GetUrl('Admin/Menu').'" method="post">';
		echo '<input type="hidden" name="cmd" value="homepage_save" />';

		echo '<h3><i class="gpicon_home"></i>';
		echo $langmessage['Homepage'];
		echo '</h3>';

		echo '<p class="homepage_setting">';
		echo '<input type="text" class="title-autocomplete gpinput" name="homepage" />';
		echo '</p>';


		echo '<p>';
		echo '<input type="submit" name="aa" value="'.htmlspecialchars($langmessage['save']).'" class="gpsubmit" data-cmd="gppost" />';
		echo ' <input type="submit" value="'.htmlspecialchars($langmessage['cancel']).'" class="admin_box_close gpcancel" /> ';
		echo '</p>';

		echo '</form>';
		echo '</div>';

	}


	/**
	 * Display the current homepage setting
	 *
	 */
	public function HomepageDisplay(){
		global $langmessage, $config;

		$label = \common::GetLabelIndex($config['homepath_key']);

		echo '<span class="gpicon_home"></span>';
		echo $langmessage['Homepage'].': ';
		echo \common::Link('Admin/Menu',$label,'cmd=homepage_select','data-cmd="gpabox"');
	}


	/**
	 * Save the posted page as the homepage
	 *
	 */
	public function HomepageSave(){
		global $langmessage, $config, $gp_index, $gp_titles, $page;

		$homepage = $_POST['homepage'];
		$homepage_key = false;
		if( isset($gp_index[$homepage]) ){
			$homepage_key = $gp_index[$homepage];
		}else{

			foreach($gp_titles as $index => $title){
				if( $title['label'] === $homepage ){
					$homepage_key = $index;
					break;
				}
			}

			if( !$homepage_key ){
				msg($langmessage['OOPS']);
				return;
			}
		}

		$config['homepath_key'] = $homepage_key;
		$config['homepath']		= \common::IndexToTitle($config['homepath_key']);
		if( !\admin_tools::SaveConfig() ){
			msg($langmessage['OOPS']);
			return;
		}

		//update the display
		ob_start();
		$this->HomepageDisplay();
		$content = ob_get_clean();

		$page->ajaxReplace[] = array('inner','.homepage_setting',$content);
	}

}
