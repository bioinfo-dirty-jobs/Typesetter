<?php

namespace gp\admin{

	defined('is_running') or die('Not an entry point...');

	class Tools{

		public static $new_versions		= array();
		public static $update_status	= 'checklater';
		public static $show_toolbar		= true;


		/**
		 * Check available versions of gpEasy and addons
		 * @static
		 *
		 */
		public static function VersionsAndCheckTime(){
			global $config, $dataDir, $gpLayouts;

			$data_timestamp = self::VersionData($version_data);

			//check core version
			// only report new versions if it's a root install
			if( gp_remote_update && !defined('multi_site_unique') && isset($version_data['packages']['core']) ){
				$core_version = $version_data['packages']['core']['version'];

				if( $core_version && version_compare(gpversion,$core_version,'<') ){
					self::$new_versions['core'] = $core_version;
				}
			}


			//check addon versions
			if( isset($config['addons']) && is_array($config['addons']) ){
				self::CheckArray($config['addons'],$version_data);
			}

			//check theme versions
			if( isset($config['themes']) && is_array($config['themes']) ){
				self::CheckArray($config['themes'],$version_data);
			}

			//check layout versions
			self::CheckArray($gpLayouts,$version_data);


			// checked recently
			$diff = time() - $data_timestamp;
			if( $diff < 604800 ){
				return;
			}

			//determin check in type
			if( !\gp\tool\RemoteGet::Test() ){
				self::VersionData($version_data);
				self::$update_status = 'checkincompat';
				return;
			}

			self::$update_status = 'embedcheck';
		}


		/**
		 * Get or cache data about available versions of gpEasy and addons
		 *
		 */
		public static function VersionData(&$update_data){
			global $dataDir;

			$file = $dataDir.'/data/_updates/updates.php';

			//set
			if( !is_null($update_data) ){
				return \gp\tool\Files::SaveData($file,'update_data',$update_data);
			}


			$update_data	= \gp\tool\Files::Get('_updates/updates','update_data');
			$update_data	+= array('packages'=>array());

			return \gp\tool\Files::$last_modified;
		}


		public static function CheckArray($array,$update_data){

			foreach($array as $addon => $addon_info){

				$addon_id = false;
				if( isset($addon_info['id']) ){
					$addon_id = $addon_info['id'];
				}elseif( isset($addon_info['addon_id']) ){ //for layouts
					$addon_id = $addon_info['addon_id'];
				}

				if( !$addon_id || !isset($update_data['packages'][$addon_id]) ){
					continue;
				}


				$installed_version = 0;
				if( isset($addon_info['version']) ){
					$installed_version = $addon_info['version'];
				}


				$new_addon_info = $update_data['packages'][$addon_id];
				$new_addon_version = $new_addon_info['version'];
				if( version_compare($installed_version,$new_addon_version,'>=') ){
					continue;
				}

				//new version found
				if( !isset($new_addon_info['name']) && isset($addon_info['name']) ){
					$new_addon_info['name'] = $addon_info['name'];
				}
				self::$new_versions[$addon_id] = $new_addon_info;
			}

		}


		public static function AdminScripts(){
			global $langmessage, $config;
			$scripts = array();


			// Content
			$scripts['Admin/Menu']						= array(	'class'		=> '\gp\admin\Menu',
																	'method'	=> 'RunScript',
																	'label'		=> $langmessage['file_manager'],
																	'group'		=> 'content',
																	);

			$scripts['Admin/Menu/Menus']				= array(	'class'		=> '\gp\admin\Menu\Menus',
																	'method'	=> 'RunScript',
																	);

			$scripts['Admin/Menu/Ajax']					= array(	'class'		=> '\gp\admin\Menu\Ajax',
																	'method'	=> 'RunScript',
																	);


			$scripts['Admin/Uploaded']					= array(	'class'		=> '\gp\admin\Content\Uploaded',
																	'label'		=> $langmessage['uploaded_files'],
																	'group'		=> 'content',
																	);


			$scripts['Admin/Extra']						= array(	'class'		=> '\gp\admin\Content\Extra',
																	'label'		=> $langmessage['theme_content'],
																	'group'		=> 'content',
																	);


			$scripts['Admin/Galleries']					= array(	'class'		=> '\gp\admin\Content\Galleries',
																	'label'		=> $langmessage['galleries'],
																	'group'		=> 'content',
																	);


			$scripts['Admin/Trash']						= array(	'class'		=> '\gp\admin\Content\Trash',
																	'label'		=> $langmessage['trash'],
																	'group'		=> 'content',
																	);


			// Appearance
			$scripts['Admin_Theme_Content']['class'] = '\gp\admin\Layout';
			$scripts['Admin_Theme_Content']['method'] = 'RunScript';
			$scripts['Admin_Theme_Content']['label'] = $langmessage['Appearance'];
			$scripts['Admin_Theme_Content']['group'] = 'appearance';


			$scripts['Admin_Theme_Content/Edit']		= array(	'class'		=> '\gp\admin\Layout\Edit',
																	'label'		=> $langmessage['Appearance'],
																	);


			$scripts['Admin_Theme_Content/Available']	 = array(	'class'		=> '\gp\admin\Layout\Available',
																	'method'	=> 'ShowAvailable',
																	'label' 	=> $langmessage['Available'],
																	);

			if( gp_remote_themes ){
				$scripts['Admin_Theme_Content/Remote']	 = array(	'class'		=> '\gp\admin\Layout',
																	'method'	=> 'RemoteBrowse',
																	'label' 	=> $langmessage['Search'],
																	);
			}



			// Settings
			$scripts['Admin/Configuration']['class'] = '\gp\admin\Configuration';
			$scripts['Admin/Configuration']['label'] = $langmessage['configuration'];
			$scripts['Admin/Configuration']['group'] = 'settings';

			$scripts['Admin/Configuration/CDN']['class'] = '\gp\admin\Configuration\CDN';
			$scripts['Admin/Configuration/CDN']['label'] = 'CDN';
			$scripts['Admin/Configuration/CDN']['group'] = 'settings';



			$scripts['Admin/Users']						= array(	'class'		=> 'gp\admin\Settings\Users',
																	'label'		=> $langmessage['user_permissions'],
																	'group'		=> 'settings',
																);

			$scripts['Admin/CKEditor']					= array(	'class'		=> 'gp\admin\Settings\CKEditor',
																	'label'		=> 'CKEditor',
																	'group'		=> 'settings',
																);

			$scripts['Admin/Classes']					= array(	'class'		=> 'gp\admin\Settings\Classes',
																	'label'		=> 'Classes',
																	'group'		=> 'settings',
																);

			$scripts['Admin/Permalinks']				= array(	'class'		=> 'gp\admin\Settings\Permalinks',
																	'label'		=> $langmessage['permalinks'],
																	'group'		=> 'settings',
																);

			$scripts['Admin/Missing']					= array(	'class'		=> 'gp\admin\Settings\Missing',
																	'label'		=> $langmessage['Link Errors'],
																	'group'		=> 'settings',
																);


			if( isset($config['admin_links']) && is_array($config['admin_links']) ){
				$scripts += $config['admin_links'];
			}


			// Tools
			$scripts['Admin/Port']		= array(	'class'		=> '\gp\admin\Tools\Port',
													'label'		=> $langmessage['Export'],
													'group'		=> 'tools',
													'method'	=> 'RunScript'
												);


			$scripts['Admin/Status']	= array(	'class'		=> '\gp\admin\Tools\Status',
													'label'		=> $langmessage['Site Status'],
													'group'		=> 'tools'
												);


			$scripts['Admin/Uninstall']	= array(	'class'		=> '\gp\admin\Tools\Uninstall',
													'label'		=> $langmessage['uninstall_prep'],
													'group'		=> 'tools'
												);


			$scripts['Admin/Cache']		= array(	'class'		=> '\gp\admin\Tools\Cache',
													'label'		=> $langmessage['Resource Cache'],
													'group'		=> 'tools'
												);



			// Unlisted
			$scripts['Admin/Addons']				= array(	'class'		=> '\gp\admin\Addons',
																'method'	=> 'RunScript',
																'label' 	=> $langmessage['plugins'],
													);

			$scripts['Admin/Addons/Available']		= array(	'class'		=> '\gp\admin\Addons',
																'method'	=> 'ShowAvailable',
																'label' 	=> $langmessage['Available'],
													);

			if( gp_remote_plugins ){
				$scripts['Admin/Addons/Remote']		= array(	'class'		=> '\gp\admin\Addons',
																'method'	=> 'RemoteBrowse',
																'label' 	=> $langmessage['Search'],
													);
			}


			$scripts['Admin/Errors']				= array(	'class'		=> '\gp\admin\Tools\Errors',
																'label' 	=> 'Errors',
													);


			$scripts['Admin/About']					= array(	'class'		=> '\gp\admin\About',
																'label' 	=> 'About gpEasy',
													);

			$scripts['Admin/Browser']				= array(	'class'		=> '\gp\admin\Content\Browser',
													);


			$scripts['Admin/Preferences']			= array(	'class'		=> '\gp\admin\Settings\Preferences',
																'label' 	=> $langmessage['Preferences'],
													);


			gpSettingsOverride('admin_scripts',$scripts);

			return $scripts;
		}


		/**
		 * Determine if the current user has permissions for the $script
		 * @static
		 * @return bool
		 */
		public static function HasPermission($script){
			global $gpAdmin;
			if( is_array($gpAdmin) ){
				$gpAdmin += array('granted'=>'');
				return self::CheckPermission($gpAdmin['granted'],$script);
			}
			return false;
		}

		/**
		 * Determine if a user has permissions for the $script
		 * @static
		 * @since 3.0b2
		 * @return bool
		 */
		public static function CheckPermission($granted,$script){

			if( $granted == 'all' ){
				return true;
			}

			$script		= str_replace('/','_',$script);
			$granted	= ','.$granted.',';

			if( strpos($granted,','.$script.',') !== false ){
				return true;
			}

			return false;

		}

		/**
		 * Determine if a user can edit a specific page
		 * @static
		 * @since 3.0b2
		 * @param string $index The data index of the page
		 * @return bool
		 */
		public static function CanEdit($index){
			global $gpAdmin;

			//pre 3.0 check
			if( !isset($gpAdmin['editing']) ){
				return self::HasPermission('file_editing');
			}

			if( $gpAdmin['editing'] == 'all' ){
				return true;
			}

			if( strpos($gpAdmin['editing'],','.$index.',') !== false ){
				return true;
			}
			return false;
		}


		/**
		 * Used to update the basic 'file_editing' permission value to the new 'editing' value used in 3.0b2+
		 * @since 3.0b2
		 * @static
		 */
		public static function EditingValue(&$user_info){
			if( isset($user_info['editing']) ){
				return;
			}
			if( self::CheckPermission($user_info['granted'],'file_editing') ){
				$user_info['editing'] = 'all';
				return 'all';
			}
			$user_info['editing'] = '';
		}



		/**
		 * Output the main admin toolbar
		 * @static
		 */
		public static function GetAdminPanel(){
			global $page, $gpAdmin;

			//don't send the panel when it's a gpreq=json request
			if( !self::$show_toolbar ){
				return;
			}

			$reqtype = \gp\tool::RequestType();
			if( $reqtype != 'template' && $reqtype != 'admin' ){
				return;
			}

			$class = '';
			$position = '';

			if( \gp\tool::RequestType() != 'admin' ){
				$position = ' style="top:'.max(-10,$gpAdmin['gpui_ty']).'px;left:'.max(-10,$gpAdmin['gpui_tx']).'px"';
				if( isset($gpAdmin['gpui_cmpct']) && $gpAdmin['gpui_cmpct'] ){
					$class = ' compact';
					if( $gpAdmin['gpui_cmpct'] === 2 ){
						$class = ' compact min';
					}elseif( $gpAdmin['gpui_cmpct'] === 3 ){
						$class = ' minb';
					}
				}
			}

			$class = ' class="keep_viewable'.$class.'"';


			echo "\n\n";
			echo '<div id="simplepanel"'.$class.$position.'><div>';

				//toolbar
				echo '<div class="toolbar">';
					echo '<a class="toggle_panel" data-cmd="toggle_panel"></a>';
					echo \gp\tool::Link('','<i class="gpicon_home"></i>');
					echo \gp\tool::Link('Admin','<i class="gpicon_admin"></i>');
					echo \gp\tool::Link('special_gpsearch','<i class="gpicon_search"></i>','',array('data-cmd'=>'gpabox'));
					echo '<a class="extra admin_arrow_out"></a>';
				echo '</div>';


				self::AdminPanelLinks(true);

			echo '</div></div>'; //end simplepanel

			echo "\n\n";

			self::AdminToolbar();
		}


		/**
		 * Show Admin Toolbar
		 *
		 */
		public static function AdminToolbar(){
			global $page, $langmessage;

			if( !method_exists($page,'AdminLinks') ){
				return;
			}

			if( isset($GLOBALS['GP_ARRANGE_CONTENT']) ){
				return;
			}

			$links = $page->AdminLinks();

			if( empty($links) ){
				return;
			}

			echo '<div id="admincontent_panel" class="fixed toolbar">';
			echo '<ul>';

			//current page
			echo '<li><b>'.$langmessage['Current Page'].':</b></li>';


			//editable areaas
			echo '<li><a data-cmd="editable_list"><i class="fa fa-edit"></i> '.$langmessage['Editable Areas'].'</a></li>';

			//admin_link
			self::FormatAdminLinks($links);

			echo '</ul>';


			if( $page->pagetype == 'display' && \gp\admin\Tools::CanEdit($page->gp_index) ){
				echo \gp\tool::Link($page->title,'<i class="fa fa-th"></i> '.$langmessage['Manage Sections'],'cmd=ManageSections',array('data-cmd'=>'inline_edit_generic','data-arg'=>'manage_sections','style'=>'float:right'));
			}

			//self::ToolbarSearch();

			echo '</div>';
		}

		public static function ToolbarSearch(){
			echo '<form method="get" action="'.\gp\tool::GetUrl('special_gpsearch').'" id="panel_search" class="cf">';

			echo '<span>';
			echo '<input type="text" value="" name="q"> ';
			echo '<i class="fa fa-search"></i>';
			echo '</span>';

			echo '<button class="gpabox" type="submit"></button> ';
			echo '</form>';
		}

		public static function FormatAdminLinks($links){
			foreach($links as $label => $link){
				echo '<li>';

				if( is_numeric($label) ){

					if( is_array($link) ){
						echo call_user_func_array(array('\\gp\\tool','Link'),$link); /* preferred */
					}else{
						echo $link; //just a text label
					}
					echo '<li>';
					continue;
				}


				if( empty($link) ){
					echo '<span>';
					echo $label;
					echo '</span>';

				}elseif( is_array($link) ){
					echo '<a data-cmd="expand"><i class="fa fa-caret-down"></i> '.$label.'</a>';
					echo '<ul>';
					self::FormatAdminLinks($link);
					echo '</ul>';

				}else{
					echo '<a href="'.$link.'">';
					echo $label;
					echo '</a>';
				}

				echo '</li>';
			}
		}



		/**
		 * Output the link areas that are displayed in the main admin toolbar and admin_main
		 * @param bool $in_panel Whether or not the links will be displayed in the toolbar
		 * @static
		 */
		public static function AdminPanelLinks($in_panel=true){
			global $langmessage, $page, $gpAdmin;

			$expand_class = 'expand_child';
			$id_piece = '';
			if( !$in_panel ){
				$expand_class = 'expand_child_click';
				$id_piece = '_click';
			}



			//content
			if( $links = self::GetAdminGroup('content') ){
				echo '<div class="panelgroup" id="panelgroup_content'.$id_piece.'">';
				self::PanelHeading($in_panel, $langmessage['Content'], 'icon_page', 'con' );
				echo '<ul class="submenu">';
				echo '<li class="submenu_top"><a class="submenu_top">'.$langmessage['Content'].'</a></li>';
				echo $links;
				echo '</ul>';
				echo '</div>';
				echo '</div>';
			}


			//appearance
			if( $links = self::GetAppearanceGroup($in_panel) ){
				echo '<div class="panelgroup" id="panelgroup_appearance'.$id_piece.'">';
				self::PanelHeading($in_panel, $langmessage['Appearance'], 'icon_app', 'app' );
				echo '<ul class="submenu">';
				echo '<li class="submenu_top"><a class="submenu_top">'.$langmessage['Appearance'].'</a></li>';
				echo $links;
				echo '</ul>';
				echo '</div>';
				echo '</div>';
			}


			//add-ons
			$links = self::GetAddonLinks($in_panel);
			if( !empty($links) ){
				echo '<div class="panelgroup" id="panelgroup_addons'.$id_piece.'">';
				self::PanelHeading($in_panel, $langmessage['plugins'], 'icon_plug', 'add' );
				echo '<ul class="submenu">';
				echo '<li class="submenu_top"><a class="submenu_top">'.$langmessage['plugins'].'</a></li>';
				echo $links;
				echo '</ul>';
				echo '</div>';
				echo '</div>';
			}


			//settings
			if( $links = self::GetAdminGroup('settings') ){
				echo '<div class="panelgroup" id="panelgroup_settings'.$id_piece.'">';
				self::PanelHeading($in_panel, $langmessage['Settings'], 'icon_edapp', 'set' );
				echo '<ul class="submenu">';
				echo '<li class="submenu_top"><a class="submenu_top">'.$langmessage['Settings'].'</a></li>';
				echo $links;
				echo '</ul>';
				echo '</div>';
				echo '</div>';
			}

			//tools
			if( $links = self::GetAdminGroup('tools') ){
				echo '<div class="panelgroup" id="panelgroup_settings'.$id_piece.'">';
				self::PanelHeading($in_panel, $langmessage['Tools'], 'icon_edapp', 'tool' );
				echo '<ul class="submenu">';
				echo '<li class="submenu_top"><a class="submenu_top">'.$langmessage['Tools'].'</a></li>';
				echo $links;
				echo '</ul>';
				echo '</div>';
				echo '</div>';
			}


			//updates
			if( count(self::$new_versions) > 0 ){

				ob_start();
				if( gp_remote_update && isset(self::$new_versions['core']) ){
					echo '<li>';
					echo '<a href="'.\gp\tool::GetDir('/include/install/update.php').'">gpEasy '.self::$new_versions['core'].'</a>';
					echo '</li>';
				}

				foreach(self::$new_versions as $addon_id => $new_addon_info){
					if( !is_numeric($addon_id) ){
						continue;
					}

					$label = $new_addon_info['name'].':  '.$new_addon_info['version'];
					if( $new_addon_info['type'] == 'theme' && gp_remote_themes ){
						$url = 'Themes';
					}elseif( $new_addon_info['type'] == 'plugin' && gp_remote_plugins ){
						$url = 'Plugins';
					}else{
						continue;
					}

					echo '<li><a href="'.addon_browse_path.'/'.$url.'/'.$addon_id.'" data-cmd="remote">'.$label.'</a></li>';

				}

				$list = ob_get_clean();
				if( !empty($list) ){
					echo '<div class="panelgroup" id="panelgroup_versions'.$id_piece.'">';
					self::PanelHeading($in_panel, $langmessage['updates'], 'icon_rfrsh', 'upd' );
					echo '<ul class="submenu">';
					echo '<li class="submenu_top"><a class="submenu_top">'.$langmessage['updates'].'</a></li>';
					echo $list;
					echo '</ul>';
					echo '</div>';
					echo '</div>';
				}

			}


			//username
			echo '<div class="panelgroup" id="panelgroup_user'.$id_piece.'">';

				self::PanelHeading($in_panel, $gpAdmin['useralias'], 'icon_user', 'use' );

				echo '<ul class="submenu">';
				echo '<li class="submenu_top"><a class="submenu_top">'.$gpAdmin['username'].'</a></li>';
				self::GetFrequentlyUsed($in_panel);

				echo '<li>';
				echo \gp\tool::Link('Admin/Preferences',$langmessage['Preferences']);
				echo '</li>';

				echo '<li>';
				echo \gp\tool::Link($page->title,$langmessage['logout'],'cmd=logout',array('data-cmd'=>'creq'));
				echo '</li>';

				echo '<li>';
				echo \gp\tool::Link('Admin/About','About gpEasy');
				echo '</li>';
				echo '</ul>';
				echo '</div>';
			echo '</div>';



			//gpEasy stats
			echo '<div class="panelgroup" id="panelgroup_gpeasy'.$id_piece.'">';
				self::PanelHeading($in_panel, $langmessage['Performance'], 'icon_chart', 'gpe' );
				echo '<ul class="submenu">';
				echo '<li class="submenu_top"><a class="submenu_top">'.$langmessage['Performance'].'</a></li>';
				echo '<li><span><span gpeasy-memory-usage>?</span> Memory</span></li>';
				echo '<li><span><span gpeasy-memory-max>?</span> Max Memory</span></li>';
				echo '<li><span><span gpeasy-seconds>?</span> Seconds</span></li>';
				echo '<li><span><span gpeasy-ms>?</span> Milliseconds</span></li>';
				echo '<li><span>0 DB Queries</span></li>';
				echo '</ul>';
			echo '</div>';
			echo '</div>';

			//resources
			if( $page->pagetype === 'admin_display' ){
				echo '<div class="panelgroup" id="panelgroup_resources'.$id_piece.'">';
				self::PanelHeading($in_panel, $langmessage['resources'], 'icon_page_gear', 'res' );
				echo '<ul class="submenu">';
				if( gp_remote_plugins && self::HasPermission('Admin_Addons') ){
					echo '<li>'.\gp\tool::Link('Admin/Addons/Remote',$langmessage['Download Plugins']).'</li>';
				}
				if( gp_remote_themes && self::HasPermission('Admin_Theme_Content') ){
					echo '<li>'.\gp\tool::Link('Admin_Theme_Content/Remote',$langmessage['Download Themes']).'</li>';
				}
				echo '<li><a href="http://gpeasy.com/Forum">Support Forum</a></li>';
				echo '<li><a href="http://gpeasy.com/Services">Service Providers</a></li>';
				echo '<li><a href="http://gpeasy.com">Official gpEasy Site</a></li>';
				echo '<li><a href="https://github.com/oyejorge/gpEasy-CMS/issues">Report A Bug</a></li>';
				echo '</ul>';
				echo '</div>';
				echo '</div>';

				if( $in_panel ){
					echo '<div class="gpversion">';
					echo 'gpEasy '.gpversion;
					echo '</div>';
				}

			}

		}

		public static function PanelHeading( $in_panel, $label, $icon, $arg ){
			global $gpAdmin;

			if( !$in_panel ){
				//echo '<span class="'.$icon.'"><span>'.$label.'</span></span>';
				echo '<span>';
				echo '<i class="gp'.$icon.'"></i>';
				echo '<span>'.$label.'</span>';
				echo '</span>';
				echo '<div class="panelgroup2">';
				return;
			}

			//echo '<a class="toplink '.$icon.'" data-cmd="toplink" data-arg="'.$arg.'"><span>';
			echo '<a class="toplink" data-cmd="toplink" data-arg="'.$arg.'">';
			echo '<i class="gp'.$icon.'"></i>';
			echo '<span>'.$label.'</span>';
			echo '</a>';

			if( $gpAdmin['gpui_vis'] == $arg ){
				echo '<div class="panelgroup2 in_window">';
			}else{
				echo '<div class="panelgroup2 in_window nodisplay">';
			}

		}

		/**
		 * Output the html used for inline editor toolbars
		 * @static
		 */
		public static function InlineEditArea(){
			global $langmessage;

			//inline editor html
			echo '<div id="ckeditor_wrap" class="nodisplay">';
			echo '<div id="ckeditor_area">';
			echo '<div class="cf">';

				echo '<div class="tools">';

				echo '<div id="ckeditor_top"></div>';

				echo '<div id="ckeditor_controls"><div id="ckeditor_save">';
				echo '<a data-cmd="ck_save" class="ckeditor_control">'.$langmessage['save'].'</a>';
				echo '<a data-cmd="ck_close" class="ckeditor_control">'.$langmessage['Close'].'</a>';
				echo '<a data-cmd="ck_save" data-arg="ck_close" class="ckeditor_control">'.$langmessage['Save & Close'].'</a>';
				echo '</div></div>';

				echo '<div id="ckeditor_bottom"></div>';

				echo '</div>';

			echo '</div>';
			echo '</div>';
			echo '</div>';


		}

		/**
		 * Get the links for the Frequently Used section of the admin toolbar
		 *
		 */
		public static function GetFrequentlyUsed($in_panel){
			global $langmessage, $gpAdmin;

			$expand_class = 'expand_child';
			if( !$in_panel ){
				$expand_class = 'expand_child_click';
			}

			//frequently used
			echo '<li class="'.$expand_class.'">';
				echo '<a>';
				echo $langmessage['frequently_used'];
				echo '</a>';
				if( $in_panel ){
					echo '<ul class="in_window">';
				}else{
					echo '<ul>';
				}
				$scripts = self::AdminScripts();
				$add_one = true;
				if( isset($gpAdmin['freq_scripts']) ){
					foreach($gpAdmin['freq_scripts'] as $link => $hits ){
						if( isset($scripts[$link]) && isset($scripts[$link]['label']) ){
							echo '<li>';
							echo \gp\tool::Link($link,$scripts[$link]['label']);
							echo '</li>';
							if( $link === 'Admin/Menu' ){
								$add_one = false;
							}
						}
					}
					if( $add_one && count($gpAdmin['freq_scripts']) >= 5 ){
						$add_one = false;
					}
				}
				if( $add_one ){
					echo '<li>';
					echo \gp\tool::Link('Admin/Menu',$scripts['Admin/Menu']['label']);
					echo '</li>';
				}
				echo '</ul>';
			echo '</li>';
		}


		//uses $status from update codes to execute some cleanup code on a regular interval (7 days)
		public static function ScheduledTasks(){
			global $dataDir;

			switch(self::$update_status){
				case 'embedcheck':
				case 'checkincompat':
					//these will continue
				break;

				case 'checklater':
				default:
				return;
			}

			self::CleanCache();

		}


		/**
		 * Delete all files older than 2 weeks
		 * If there are more than 200 files older than one week
		 *
		 */
		public static function CleanCache(){
			global $dataDir;
			$dir = $dataDir.'/data/_cache';
			$files = scandir($dir);
			$times = array();
			foreach($files as $file){
				if( $file == '.' || $file == '..' || strpos($file,'.php') !== false ){
					continue;
				}
				$full_path	= $dir.'/'.$file;
				$time		= filemtime($full_path);
				$diff		= time() - $time;

				//if relatively new ( < 3 days), don't delete it
				if( $diff < 259200 ){
					continue;
				}

				//if old ( > 14 days ), delete it
				if( $diff > 1209600 ){
					\gp\tool\Files::RmAll($full_path);
					continue;
				}
				$times[$file] = $time;
			}

			//reduce further if needed till we have less than 200 files
			arsort($times);
			$times = array_keys($times);
			while( count($times) > 200 ){
				$full_path = $dir.'/'.array_pop($times);
				\gp\tool\Files::RmAll($full_path);
			}
		}


		public static function AdminHtml(){
			global $page, $gp_admin_html;

			ob_start();


			self::InlineEditArea();

			echo '<div class="nodisplay" id="gp_hidden"></div>';

			if( isset($page->admin_html) ){
				echo $page->admin_html;
			}

			self::GetAdminPanel();


			self::CheckStatus();
			self::ScheduledTasks();
			$gp_admin_html = ob_get_clean() . $gp_admin_html;

		}

		public static function CheckStatus(){

			switch(self::$update_status){
				case 'embedcheck':
					$img_path = \gp\tool::GetUrl('Admin','cmd=embededcheck');
					\gp\tool::IdReq($img_path);
				break;
				case 'checkincompat':
					$img_path = \gp\tool::IdUrl('ci'); //check in
					\gp\tool::IdReq($img_path);
				break;
			}
		}



		public static function GetAdminGroup($grouping){
			global $langmessage,$page;

			$scripts = self::AdminScripts();

			ob_start();
			foreach($scripts as $script => $info){

				if( !isset($info['group']) || $info['group'] !== $grouping ){
					continue;
				}

				if( !self::HasPermission($script) ){
					continue;
				}
				echo '<li>';

				if( isset($info['popup']) && $info['popup'] == true ){
					echo \gp\tool::Link($script,$info['label'],'',array('data-cmd'=>'gpabox'));
				}else{
					echo \gp\tool::Link($script,$info['label']);
				}


				echo '</li>';

				switch($script){
					case 'Admin/Menu':
						echo '<li>';
						echo \gp\tool::Link('Admin/Menu/Ajax','+ '.$langmessage['create_new_file'],'cmd=AddHidden&redir=redir',array('title'=>$langmessage['create_new_file'],'data-cmd'=>'gpabox'));
						echo '</li>';
					break;
				}

			}


			$result = ob_get_clean();
			if( !empty($result) ){
				return $result;
			}
			return false;
		}

		public static function GetAppearanceGroup($in_panel){
			global $page, $langmessage, $gpLayouts, $config;

			if( !self::HasPermission('Admin_Theme_Content') ){
				return false;
			}

			ob_start();

			echo '<li>';
			echo \gp\tool::Link('Admin_Theme_Content',$langmessage['manage']);
			echo '</li>';

			if( !empty($page->gpLayout) ){
				echo '<li>';
				echo \gp\tool::Link('Admin_Theme_Content/Edit/'.urlencode($page->gpLayout),$langmessage['edit_this_layout']);
				echo '</li>';
			}
			echo '<li>';
			echo \gp\tool::Link('Admin_Theme_Content/Available',$langmessage['available_themes']);
			echo '</li>';
			if( gp_remote_themes ){
				echo '<li>';
				echo \gp\tool::Link('Admin_Theme_Content/Remote',$langmessage['Download Themes']);
				echo '</li>';
			}

			//list of layouts
			$expand_class = 'expand_child';
			if( !$in_panel ){
				$expand_class = 'expand_child_click';
			}

			echo '<li class="'.$expand_class.'">';
			echo '<a>'.$langmessage['layouts'].'</a>';
			if( $in_panel ){
				echo '<ul class="in_window">';
			}else{
				echo '<ul>';
			}


			if( !empty($page->gpLayout) ){
				$to_hightlight = $page->gpLayout;
			}else{
				$to_hightlight = $config['gpLayout'];
			}

			foreach($gpLayouts as $layout => $info){
				if( $to_hightlight == $layout ){
					echo '<li class="selected">';
				}else{
					echo '<li>';
				}

				$display = '<span class="layout_color_id" style="background-color:'.$info['color'].';"></span>&nbsp; '.$info['label'];
				echo \gp\tool::Link('Admin_Theme_Content/Edit/'.rawurlencode($layout),$display);
				echo '</li>';
			}
			echo '</ul>';
			echo '</li>';

			return ob_get_clean();
		}



		/**
		 * Clean a string for use in a page label
		 * Some tags will be allowed
		 *
		 */
		public static function PostedLabel($string){

			// Remove control characters
			$string = preg_replace( '#[[:cntrl:]]#u', '', $string ) ; //[\x00-\x1F\x7F]

			//change known entities to their character equivalent
			$string = \gp\tool\Strings::entity_unescape($string);

			return self::LabelHtml($string);
		}

		/**
		 * Convert a label to a slug
		 * Does not use PostedSlug() so entity_unescape isn't called twice
		 * @since 2.5b1
		 *
		 */
		public static function LabelToSlug($string){
			return self::PostedSlug( $string, true);
		}


		/**
		 * Clean a slug posted by the user
		 * @param string $slug The slug provided by the user
		 * @return string
		 * @since 2.4b5
		 */
		public static function PostedSlug($string, $from_label = false){
			global $config;

			$orig_string = $string;

			// Remove control characters
			$string = preg_replace( '#[[:cntrl:]]#u', '', $string ) ; // 	[\x00-\x1F\x7F]

			//illegal characters
			$string = str_replace( array('?','*',':','|'), array('','','',''), $string);

			//change known entities to their character equivalent
			$string = \gp\tool\Strings::entity_unescape($string);


			//if it's from a label, remove any html
			if( $from_label ){
				$string = self::LabelHtml($string);
				$string = strip_tags($string);

				//after removing tags, unescape special characters
				$string = str_replace( array('&lt;','&gt;','&quot;','&#39;','&amp;'), array('<','>','"',"'",'&'), $string);
			}

			// # character after unescape for entities and unescape of special chacters when $from_label is true
			$string = str_replace('#','',$string);

			//slashes
			$string = self::SlugSlashes($string);

			$string = str_replace(' ',$config['space_char'],$string);

			return \gp\tool\Plugins::Filter('PostedSlug',array($string, $orig_string, $from_label));
		}

		/**
		 * Fix the html for page labels
		 *
		 */
		public static function LabelHtml($string){

			//prepend with space for preg_split(), space will be trimmed at the end
			$string = ' '.$string;

			//change non html entity uses of & to &amp; (not exact but should be sufficient)
			$pieces = preg_split('#(&(?:\#[0-9]{2,4}|[a-zA-Z0-9]{2,8});)#',$string,0,PREG_SPLIT_DELIM_CAPTURE);
			$string = '';
			for($i=0;$i<count($pieces);$i++){
				if( $i%2 ){
					$string .= $pieces[$i];
				}else{
					$string .= str_replace('&','&amp;',$pieces[$i]);
				}
			}

			//change non html tag < and > into &lt; and &gt;
			$pieces = preg_split('#(<(?:/?)[a-zA-Z0-9][^<>]*>)#',$string,0,PREG_SPLIT_DELIM_CAPTURE);
			$string = '';
			for($i=0;$i< count($pieces);$i++){
				if( $i%2 ){
					$string .= $pieces[$i];
				}else{
					$string .= \gp\tool::LabelSpecialChars($pieces[$i]);
				}
			}

			//only allow tags that are legal to be inside <a> except for <script>.Per http://www.w3.org/TR/xhtml1/dtds.html#dtdentry_xhtml1-strict.dtd_a.content
			$string = strip_tags($string,'<abbr><acronym><b><big><bdo><br><button><cite><code><del><dfn><em><kbd><i><img><input><ins><label><map><object><q><samp><select><small><span><sub><sup><strong><textarea><tt><var>');

			return trim($string);
		}


		/**
		 * Remove slashes and dots from a slug that could cause navigation problems
		 *
		 */
		public static function SlugSlashes($string){

			$string = str_replace('\\','/',$string);

			//remove leading "./"
			$string = preg_replace('#^\.+[\\\\/]#','/',$string);

			//remove trailing "/."
			$string = preg_replace('#[\\\\/]\.+$#','/',$string);

			//remove any "/./"
			$string = preg_replace('#[\\\\/]\.+[\\\\/]#','/',$string);

			//remove consecutive slashes
			$string = preg_replace('#[\\\\/]+#','/',$string);

			if( $string == '.' ){
				return '';
			}

			return ltrim($string,'/');
		}



		/**
		 * Case insenstively check the title against all other titles
		 *
		 * @param string $title The title to be checked
		 * @return mixed false or the data index of the matched title
		 * @since 2.4b5
		 */
		public static function CheckTitleCase($title){
			global $gp_index;

			$titles_lower = array_change_key_case($gp_index,CASE_LOWER);
			$title_lower = strtolower($title);
			if( isset($titles_lower[$title_lower]) ){
				return $titles_lower[$title_lower];
			}

			return false;
		}

		/**
		 * Check a title against existing titles, special pages and reserved unique string
		 *
		 * @param string $title The title to be checked
		 * @return mixed false if the title doesn't exist, string if a conflict is found
		 * @since 2.4b5
		 */
		public static function CheckTitle($title,&$message){
			global $gp_index, $config, $langmessage;

			if( empty($title) ){
				$message = $langmessage['TITLE_REQUIRED'];
				return false;
			}

			if( isset($gp_index[$title]) ){
				$message = $langmessage['TITLE_EXISTS'];
				return false;
			}

			$type = \gp\tool::SpecialOrAdmin($title);
			if( $type !== false ){
				$message = $langmessage['TITLE_RESERVED'];
				return false;
			}

			$prefix = substr($config['gpuniq'],0,7).'_';
			if( strpos($title,$prefix) !== false ){
				$message = $langmessage['TITLE_RESERVED'].' (2)';
				return false;
			}

			if( strlen($title) > 100 ){
				$message = $langmessage['LONG_TITLE'];
				return false;
			}

			return true;
		}

		/**
		 * Check a title against existing titles and special pages
		 *
		 */
		public static function CheckPostedNewPage($title,&$message){
			global $langmessage,$gp_index, $config;

			$title = self::LabelToSlug($title);

			if( !self::CheckTitle($title,$message) ){
				return false;
			}

			if( self::CheckTitleCase($title) ){
				$message = $langmessage['TITLE_EXISTS'];
				return false;
			}

			return $title;
		}


		/**
		 * Save config.php and pages.php
		 *
		 */
		public static function SaveAllConfig(){
			if( !self::SaveConfig() ){
				return false;
			}

			if( !self::SavePagesPHP() ){
				return false;
			}
			return true;
		}

		/**
		 * Save the gpEasy configuration
		 * @return bool
		 *
		 */
		public static function SavePagesPHP(){
			global $gp_index, $gp_titles, $gp_menu, $gpLayouts, $dataDir;

			if( !is_array($gp_menu) || !is_array($gp_index) || !is_array($gp_titles) || !is_array($gpLayouts) ){
				return false;
			}

			$pages = array();
			$pages['gp_menu'] = $gp_menu;
			$pages['gp_index'] = $gp_index;
			$pages['gp_titles'] = $gp_titles;
			$pages['gpLayouts'] = $gpLayouts;

			if( !\gp\tool\Files::SaveData($dataDir.'/data/_site/pages.php','pages',$pages) ){
				return false;
			}
			return true;

		}

		/**
		 * Save the gpEasy configuration
		 * @return bool
		 *
		 */
		public static function SaveConfig(){
			global $config;

			if( !is_array($config) ) return false;

			if( !isset($config['gpuniq']) ) $config['gpuniq'] = \gp\tool::RandomString(20);

			return \gp\tool\Files::SaveData('_site/config','config',$config);
		}


		/**
		 * @deprecated
		 * used by simpleblog1
		 */
		public static function tidyFix(&$text){
			trigger_error('tidyFix should be called using gp_edit::tidyFix() instead of admin_tools:tidyFix()');
			return false;
		}



		/**
		 * Return the addon section of the admin panel
		 *
		 */
		public static function GetAddonLinks($in_panel){
			global $langmessage, $config;

			$expand_class = 'expand_child';
			if( !$in_panel ){
				$expand_class = 'expand_child_click';
			}

			ob_start();

			$addon_permissions = self::HasPermission('Admin_Addons');

			if( $addon_permissions ){
				echo '<li>';
				echo \gp\tool::Link('Admin/Addons',$langmessage['manage']);
				echo '</li>';
				if( gp_remote_plugins ){
					echo '<li class="separator">';
					echo \gp\tool::Link('Admin/Addons/Remote',$langmessage['Download Plugins']);
					echo '</li>';
				}
			}


			$show =& $config['addons'];
			if( is_array($show) ){

				foreach($show as $addon => $info){

					//backwards compat
					if( is_string($info) ){
						$addonName = $info;
					}elseif( isset($info['name']) ){
						$addonName = $info['name'];
					}else{
						$addonName = $addon;
					}

					$sublinks = self::GetAddonSubLinks($addon);

					if( !empty($sublinks) ){
						echo '<li class="'.$expand_class.'">';
						if( $in_panel ){
							$sublinks = '<ul class="in_window">'.$sublinks.'</ul>';
						}else{
							$sublinks = '<ul>'.$sublinks.'</ul>';
						}
					}else{
						echo '<li>';
					}

					if( $addon_permissions ){
						echo \gp\tool::Link('Admin/Addons/'.self::encode64($addon),$addonName);
					}else{
						echo '<a>'.$addonName.'</a>';
					}
					echo $sublinks;

					echo '</li>';
				}
			}


			return ob_get_clean();

		}

		/**
		* Determine if the installation should be allowed to process remote installations
		*
		*/
		public static function CanRemoteInstall(){
			static $bit;

			if( isset($bit) ){
				return $bit;
			}

			if( !gp_remote_themes && !gp_remote_plugins ){
				return $bit = 0;
			}

			if( !function_exists('gzinflate') ){
				return $bit = 0;
			}

			if( !\gp\tool\RemoteGet::Test() ){
				return $bit = 0;
			}

			if( gp_remote_themes ){
				$bit = 1;
			}
			if( gp_remote_plugins ){
				$bit += 2;
			}

			return $bit;
		}



		/**
		 * Return a formatted list of links associated with $addon
		 * @return string
		 */
		public static function GetAddonSubLinks($addon=false){
			global $config;

			$special_links	= self::GetAddonTitles( $addon);
			$admin_links	= self::GetAddonComponents( $config['admin_links'], $addon);


			$result = '';
			foreach($special_links as $linkName => $linkInfo){
				$result .= '<li>';
				$result .= \gp\tool::Link($linkName,$linkInfo['label']);
				$result .= '</li>';
			}

			foreach($admin_links as $linkName => $linkInfo){
				if( self::HasPermission($linkName) ){
					$result .= '<li>';
					$result .= \gp\tool::Link($linkName,$linkInfo['label']);
					$result .= '</li>';
				}
			}
			return $result;
		}




		/**
		 * Get the titles associate with $addon
		 * Similar to GetAddonComponents(), but built for $gp_titles
		 * @return array List of addon links
		 *
		 */
		public static function GetAddonTitles($addon){
			global $gp_index, $gp_titles;

			$sublinks = array();
			foreach($gp_index as $slug => $id){
				$info = $gp_titles[$id];
				if( !is_array($info) ){
					continue;
				}
				if( !isset($info['addon']) ){
					continue;
				}
				if( $info['addon'] !== $addon ){
					continue;
				}
				$sublinks[$slug] = $info;
			}
			return $sublinks;
		}

		/**
		 * Get the admin titles associate with $addon
		 * @return array List of addon links
		 *
		 */
		public static function GetAddonComponents($from,$addon){
			$result = array();

			if( !is_array($from) ){
				return $result;
			}

			foreach($from as $name => $value){
				if( !is_array($value) ){
					continue;
				}
				if( !isset($value['addon']) ){
					continue;
				}
				if( $value['addon'] !== $addon ){
					continue;
				}
				$result[$name] = $value;
			}

			return $result;
		}


		public static function FormatBytes($size, $precision = 2){
			$base = log($size) / log(1024);
			$suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
			$floor = max(0,floor($base));
			return round(pow(1024, $base - $floor), $precision) .' '. $suffixes[$floor];
		}

		/**
		 * Base convert that handles large numbers
		 *
		 */
		public static function base_convert($str, $frombase=10, $tobase=36) {
			$str = trim($str);
			if (intval($frombase) != 10) {
				$len = strlen($str);
				$q = 0;
				for ($i=0; $i<$len; $i++) {
					$r = base_convert($str[$i], $frombase, 10);
					$q = bcadd(bcmul($q, $frombase), $r);
				}
			}
			else $q = $str;

			if (intval($tobase) != 10) {
				$s = '';
				while (bccomp($q, '0', 0) > 0) {
					$r = intval(bcmod($q, $tobase));
					$s = base_convert($r, 10, $tobase) . $s;
					$q = bcdiv($q, $tobase, 0);
				}
			}
			else $s = $q;

			return $s;
		}


		/**
		 * Return the size in bytes of the /data directory
		 *
		 */
		public static function DiskUsage(){
			global $dataDir;

			$dir = $dataDir.'/data';
			return self::DirSize($dir);
		}

		public static function DirSize($dir){
			$size = 0;
			$files = scandir($dir);
			$len = count($files);
			for($i=0;$i<$len;$i++){
				$file = $files[$i];
				if( $file == '.' || $file == '..' ){
					continue;
				}
				$full_path = $dir.'/'.$file;
				if( is_link($full_path) ){
					continue;
				}
				if( is_dir($full_path) ){
					$size += self::DirSize($full_path);
					continue;
				}

				$size += filesize($full_path);
			}
			return $size;
		}

		public static function encode64( $input ){
			$encoded	= base64_encode($input);
			$encoded	= rtrim($encoded,'=');
			return strtr($encoded, '+/', '-_');
		}

		public static function decode64( $input ){
			$mod = strlen($input) % 4;
			if( $mod !== 0 ){
				$append_len	= 4 - $mod;
				$input		.= substr('===',0,$append_len);
			}
			return base64_decode(strtr($input, '-_', '+/'));
		}


		/**
		 * Return the time in a human readable string
		 *
		 */
		public static function Elapsed($difference){
			$periods = array('second', 'minute', 'hour', 'day', 'week', 'month', 'year', 'decade');
			$lengths = array('60','60','24','7','4.35','12','10');

			for($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++) {
			   $difference /= $lengths[$j];
			}

			$difference = round($difference);

			if($difference != 1) {
			   $periods[$j].= 's';
			}

			return $difference.' '.$periods[$j];
		}

		//deprecated v4.4
		public static function AdminContentPanel(){}
		public static function AdminContainer(){}
	}
}

namespace{
	class admin_tools extends \gp\admin\Tools{}
}

