<?php
/*
Plugin Name: ScreenSteps Live
Plugin URI: http://screensteps.com/blog/2008/07/screensteps-live-wordpress-plugin/
Description: This plugin will incorporate lessons from your ScreenSteps Live account into your WordPress Pages.
Version: 0.9.10
Author: Blue Mango Learning Systems
Author URI: http://www.screensteps.com
*/

//$result = error_reporting(E_ERROR�|�E_WARNING�|�E_PARSE�|�E_NOTICE);

// Global var for SSLiveWordPress object. It is shared among multiple wp callbacks.
$screenstepslivewp = NULL;


// This plugin processes content of all posts
add_filter('the_content', 'screenstepslive_parseContent', 100);
add_filter('the_title', 'screenstepslive_parseTitle', 100);
add_filter('wp_list_pages', 'screenstepslive_listPages', 100);
add_filter('delete_post', 'screenstepslive_checkIfDeletedPostIsReferenced', 100);
add_action('admin_menu', 'screenstepslive_addPages');


function screenstepslive_initializeObject()
{
	global $screenstepslivewp;
	
	if (!$screenstepslivewp) {
		// PROVIDE EXAMPLE SETTINGS AS DEFAULT
		if (get_option('screenstepslive_domain') == '' && get_option('screenstepslive_reader_name') == '') {
			update_option('screenstepslive_domain', 'example.screenstepslive.com');
			update_option('screenstepslive_reader_name', 'example');
			update_option('screenstepslive_reader_password', 'example');
			update_option('screenstepslive_protocol', 'http');
			update_option('screenstepslive_pages', '');
		}
		
		require_once(dirname(__FILE__) . '/sslivewordpress_class.php');
		
		// Create ScreenSteps Live object using your domain and API key
		$screenstepslivewp = new SSLiveWordPress(get_option('screenstepslive_domain'),
										get_option('screenstepslive_protocol'));
		$screenstepslivewp->SetUserCredentials(get_option('screenstepslive_reader_name'), get_option('screenstepslive_reader_password'));
		
		$screenstepslivewp->user_can_read_private = current_user_can('read_private_posts') == 1;
		
		/*
		//$screenstepslivewp->show_protected = true;
		$screenstepslivewp->spaces_settings = get_option('screenstepslive_spaces_settings');
		//$screenstepslivewp->manual_settings = get_option('screenstepslive_manual_settings');
		
		$screenstepslivewp->spaces_index_post_id = get_option('screenstepslive_spaces_index_post_id');
		$screenstepslivewp->space_post_id = get_option('screenstepslive_space_post_id');
		$screenstepslivewp->manual_post_id = get_option('screenstepslive_manual_post_id');
		$screenstepslivewp->bucket_post_id = get_option('screenstepslive_bucket_post_id');
		$screenstepslivewp->lesson_post_id = get_option('screenstepslive_lesson_post_id'); // For manuals. We used this before spaces.
		$screenstepslivewp->bucket_lesson_post_id = get_option('screenstepslive_bucket_lesson_post_id');
		*/
	}
	
	// Any caller will just get a reference to this object.
	return $screenstepslivewp;
}


function screenstepslive_listPages($the_output) {
	// We remove the link to the current SS Live page from the list. It's $title will be rewritten
	// by screenstepslive_parseTitle and since WordPress has one filter for ALL titles we don't have
	// a lot of options.
	$postID = get_the_ID();
	$post = &get_post($postID);
			
	// Find settings for this page
	$pages = get_option('screenstepslive_pages');
	
	foreach ($pages as $page_id => $value) {
		if ($page_id == $post->ID) {
			$page = $value;
			break;
		}
	}
		
	// Get out if we have nothing to offer.
	if (!isset($page)) return ($the_output);

	$theNewOutput = preg_replace('/\<li.*\>' . preg_quote($post->post_title, "/") . '\<.*?\/li\>/', '', $the_output, -1, $count);
	if ($count > 0) return $theNewOutput;
	else return ($the_output);
}


function screenstepslive_parseTitle($the_title) {
	if (!is_page( $the_title)) return ($the_title); // cursed wp_list_pages calls this as well.
	
	$postID = get_the_ID();
	$post = &get_post($postID);
				
	// Find settings for this page
	$pages = get_option('screenstepslive_pages');
	
	foreach ($pages as $page_id => $value) {
		if ($page_id == $post->ID) {
			$page = $value;
			break;
		}
	}
		
	// Get out if we have nothing to offer.
	if (!isset($page)) return ($the_title);
	
	// Include necessary SS Live files
	$sslivewp = screenstepslive_initializeObject();
	
	$space_id = $page['space_id'];
	$manual_id = $sslivewp->CleanseID($_GET['manual_id']);
	$bucket_id = $sslivewp->CleanseID($_GET['bucket_id']);
	if ($page['resource_type'] == 'bucket' && $page['resource_id'] > 0)
		$bucket_id = $page['resource_id'];
	else if ($page['resource_type'] == 'manual' && $page['resource_id'] > 0)
		$manual_id = $page['resource_id'];
	$lesson_id = $sslivewp->CleanseID($_GET['lesson_id']);
	
	
	if (!$space_id > 0)
	{
		// nothing to do	
	}  else if ($space_id > 0 && $lesson_id > 0) {
		if ($manual_id > 0) {
			if ($page['resource_id'] == 0)
			{
				// Page is a 'space' page.
				$the_title = '<a href="' . $sslivewp->GetLinkToSpace($post->ID, $space_id) . '">' . $post->post_title . '</a> > ' . 
							'<a href="' . $sslivewp->GetLinkToManual($post->ID, $space_id, $manual_id) . '">' . 
								$sslivewp->GetManualTitle($space_id, $manual_id) . '</a> > ' .
							$sslivewp->GetManualLessonTitle($space_id, $manual_id, $lesson_id);			
			} else
			{
				// Page is a 'manual' page.
				$the_title = '<a href="' . $sslivewp->GetLinkToManual($post->ID, $space_id, $manual_id) . '">' . $post->post_title . '</a> > ' . 
							$sslivewp->GetManualLessonTitle($space_id, $manual_id, $lesson_id);			
			}
		} else if ($bucket_id > 0) {
			if ($page['resource_id'] == 0)
			{
				// Page is a 'space' page.
				$the_title = '<a href="' . $sslivewp->GetLinkToSpace($post->ID, $space_id) . '">' . $post->post_title . '</a> > ' . 
							'<a href="' . $sslivewp->GetLinkToBucket($post->ID, $space_id, $bucket_id) . '">' . 
								$sslivewp->GetBucketTitle($space_id, $bucket_id) . '</a> > ' .
							$sslivewp->GetBucketLessonTitle($space_id, $bucket_id, $lesson_id);			
			} else
			{
				// Page is a 'manual' page.
				$the_title = '<a href="' . $sslivewp->GetLinkToBucket($post->ID, $space_id, $bucket_id) . '">' . $post->post_title . '</a> > ' . 
							$sslivewp->GetBucketLessonTitle($space_id, $bucket_id, $lesson_id);			
			}
		}
			
	} else if ($space_id > 0 && $manual_id > 0) {
		if ($page['resource_id'] == 0)
		{
			// Page is a 'space' page.
			$the_title = '<a href="' . $sslivewp->GetLinkToSpace($post->ID, $space_id) . '">' . $post->post_title . '</a> > ' . 
						$sslivewp->GetManualTitle($space_id, $manual_id);			
		} else
		{
			// Page is a 'manual' page.
			// Nothing to do.
		}
		
	} else if ($space_id > 0 && $bucket_id > 0) {		
		if ($page['resource_id'] == 0)
		{
			// Page is a 'space' page.
			$the_title = '<a href="' . $sslivewp->GetLinkToSpace($post->ID, $space_id) . '">' . $post->post_title . '</a> > ' . 
						$sslivewp->GetBucketTitle($space_id, $bucket_id);
		} else
		{
			// Page is a 'bucket' page.
			// Nothing to do.
		}

	} else {
		// Spaces. Not used.
	}
	
	return ($the_title);
}


// Called by WordPress to process content
function screenstepslive_parseContent($the_content)
{	
	$postID = get_the_ID();
	$post = &get_post($postID);

	if (stristr($the_content, '{{SCREENSTEPSLIVE_CONTENT}}') !== FALSE) {
		$text = '';
		
		// Find settings for this page
		$pages = get_option('screenstepslive_pages');
		
		foreach ($pages as $page_id => $value) {
			if ($page_id == $post->ID) {
				$page = $value;
				break;
			}
		}
		
		// Get out if we have nothing to offer.
		if (!isset($page)) return false;
		
		// Include necessary SS Live files
		$sslivewp = screenstepslive_initializeObject();
		
		$space_id = $page['space_id'];
		$manual_id = $sslivewp->CleanseID($_GET['manual_id']);
		$bucket_id = $sslivewp->CleanseID($_GET['bucket_id']);
		if ($page['resource_type'] == 'bucket' && $page['resource_id'] > 0)
			$bucket_id = $page['resource_id'];
		else if ($page['resource_type'] == 'manual' && $page['resource_id'] > 0)
			$manual_id = $page['resource_id'];
		$lesson_id = $sslivewp->CleanseID($_GET['lesson_id']);
		
		
		if (!$space_id > 0)
		{
			// Retrieve list of all spaces
			$text = $sslivewp->GetSpacesList();
			
		}  else if ($space_id > 0 && $lesson_id > 0) {
			//$text = screenstepslive_parseTitle($post->post_title);
			if ($manual_id > 0) {
				$next_link = $sslivewp->GetLinkToNextLesson($post->ID, $space_id, 'manual', $manual_id, $lesson_id, 'Next Lesson');
				$prev_link = $sslivewp->GetLinkToPrevLesson($post->ID, $space_id, 'manual', $manual_id, $lesson_id, 'Previous Lesson');
	
				if ($prev_link != '') 
					$text .= '<div>' . $prev_link . '</div>';
				if (!empty($next_link))
					$text .= '<div>' . $next_link . '</div>';
				$text .= $sslivewp->GetLessonHTML($space_id, 'manual', $manual_id, $lesson_id);
				if (!empty($prev_link))
					$text .= '<div>' . $prev_link . '</div>';
				if (!empty($next_link))
					$text .= '<div>' . $next_link . '</div>';
				
			} else if ($bucket_id > 0) {
				$text = $sslivewp->GetLessonHTML($space_id, 'manual', $manual_id, $lesson_id);
			}
			
		} else if ($space_id > 0 && $manual_id > 0) {
			//$text = screenstepslive_parseTitle($post->post_title);
			if ($page['resource_id'] == 0)
			{
				// Page is a 'space' page.
				//$text = '<h2>' . $sslivewp->GetManualTitle($space_id, $manual_id) . '</h2>' . "\n";
				//$text .= '<div><a href="' . $sslivewp->GetLinkToSpace($post->ID, $space_id) . '">Return to "' . $post->post_title . '"</a></div>' . "\n";
				$text .= $sslivewp->GetManualList($post->ID, $space_id, $manual_id);
				
			} else
			{
				// Page is a 'manual' page.
				$text .= $sslivewp->GetManualList($post->ID, $space_id, $manual_id);
			}
			
		} else if ($space_id > 0 && $bucket_id > 0) {
			//$text = screenstepslive_parseTitle($post->post_title);
			if ($page['resource_id'] == 0)
			{
				// Page is a 'space' page.
				//$text = '<h2>' . $sslivewp->GetBucketTitle($space_id, $bucket_id) . '</h2>' . "\n";
				//$text .= '<div><a href="' . $sslivewp->GetLinkToSpace($post->ID, $space_id) . '">Return to "' . $post->post_title . '"</a></div>' . "\n";
				$text .= $sslivewp->GetBucketList($post->ID, $space_id, $bucket_id);
				
			} else
			{
				// Page is a 'bucket' page.
				$text .= $sslivewp->GetBucketList($post->ID, $space_id, $bucket_id);
			}

		} else {
			$text = $sslivewp->GetSpaceList($post->ID, $space_id);
		}
	}
	
	if ($text != '') $the_content = preg_replace('/{{SCREENSTEPSLIVE_CONTENT}}/i', $text, $the_content);
	
    return $the_content;
}


function screenstepslive_checkIfDeletedPostIsReferenced($postID) {
	$pages = get_option('screenstepslive_pages');
	foreach ($pages as $i => $value) {
		if ($pages[$i]['id'] == $postID) {
			unset($pages[$i]);
		}
	}
	
	update_option('screenstepslive_pages', array_values($pages)); // array_values reindexes
}


// Use to replace page title
//$the_content = preg_replace('/{{SCREENSTEPSLIVE_LESSON_TITLE}}/i', $sslivewp->GetLessonTitle($manual_id, $lesson_id), $the_content);

// Add admin page
function screenstepslive_addPages()
{
	add_options_page('ScreenSteps Live Options', 'ScreenSteps Live', 8, __FILE__, 'screenstepslive_optionPage');
}


// Shows Admin page
function screenstepslive_optionPage()
{
	$sslivewp = screenstepslive_initializeObject();
	
	$form_submitted = false; // So we don't create pages twice.
	
	// API form was submitted
	if ($_POST['api_submitted'] == 1) {
		update_option('screenstepslive_domain', $_POST['domain']);
		update_option('screenstepslive_reader_name', $_POST['reader_name']);
		update_option('screenstepslive_reader_password', $_POST['reader_password']);
		update_option('screenstepslive_protocol', $_POST['protocol']);
		
		$sslivewp->SetUserCredentials($_POST['reader_name'], $_POST['reader_password']);
		$sslivewp->protocol = $_POST['protocol'];
		
		$form_submitted = true;
	}
	
	// Manuals form was subbmited
	if ($_POST['pages_submitted'] == 1) {		
		// Loop through posted pages, making sure they still exist. User could have deleted one.
		$pages = get_option('screenstepslive_pages');
		
		foreach ($_POST['pages'] as $page_id => $new_page) {
			if (isset($pages[$page_id])) {
				if ($pages[$page_id]['space_id'] != $new_page['space_id']) {
					$pages[$page_id]['resource_id'] = 0;
				} else {
					$pages[$page_id]['resource_id'] = $new_page['resource_id'];
				}
				$pages[$page_id]['space_id'] = $new_page['space_id'];
			}
		}
				
		update_option('screenstepslive_pages', $pages);
		
		$form_submitted = true;
	}
	
	// Create template pages
	if (!$form_submitted && isset($_GET['ssliveaction'])) {
		switch ($_GET['ssliveaction']) {
			case 'create_page':
				$postID = screenstepslive_createTemplatePage();
				if (intval($postID) > 0) {
					$spaces = $sslivewp->GetSpaces();
					
					$pages = get_option('screenstepslive_pages');
					$pages[$postID]['id'] = $postID;
					$pages[$postID]['space_id'] = $spaces['space'][0]['id'];
					$pages[$postID]['resource_type'] = 'manual';
					$pages[$postID]['resource_id'] = 0;
					
					update_option('screenstepslive_pages', $pages);
				}
				break;
		}
	}
	
	// UI	
echo <<<END
<div class="wrap">
	<h2>ScreenSteps Live</h2>
	<br />
	<fieldset class="options">
		<legend>ScreenSteps Live API Information</legend>		
END;
			
			// Print API info
			$http_option = get_option('screenstepslive_protocol') == 'http' ? ' selected="selected"' : '';
			$https_option = get_option('screenstepslive_protocol') == 'https' ? ' selected="selected"' : '';
			
			print ('<form method="post" action="' . GETENV('REQUEST_URI') . '">' . "\n");
			print ('<input type="hidden" name="api_submitted" value="1">' . "\n");
			print ('<table class="optiontable form-table">');
			print ('<tr><th scope="row" style="width:200px;">ScreenSteps Live Domain:</th><td>' . 
					'<input type="text" name="domain" id="domain" style="width:20em;" value="'. get_option('screenstepslive_domain') . '"></td></tr>');
			/*print ('<tr><th scope="row">ScreenSteps Live API Key:</th><td>' . 
					'<input type="text" name="api_key" id="api_key" value="'. get_option('screenstepslive_api_key') . '"></td></tr>');
			*/
			print ('<tr><th scope="row">ScreenSteps Live Reader Account username:</th><td>' . 
					'<input type="text" name="reader_name" id="reader_name" value="'. get_option('screenstepslive_reader_name') . '"></td></tr>');
			print ('<tr><th scope="row">ScreenSteps Live Reader Account password:</th><td>' . 
					'<input type="password" name="reader_password" id="reader_password" value="'. get_option('screenstepslive_reader_password') . '"></td></tr>');
			print ('<tr><th scope="row">Protocol:</th><td>' . 
					'<select name="protocol"><option value="http"'. $http_option . '">HTTP</option>' . 
					'<option value="https"'. $https_option . '">HTTPS</option></select>' .
					'</td></tr>');
			print ('</table>');

echo <<<END
			<div class="submit">
				<input type="submit" id="submit_api_settings" value="Save ScreenSteps Live API Settings" />
			</div>
		</form>
	</fieldset>
	
	<br />

END;

			// Print WordPress Pages

echo <<<END
	
	<fieldset class="options">
			<legend>WordPress Page Settings</legend>
END;
			
			if (!isset($spaces)) $spaces = $sslivewp->GetSpaces();
			if ($spaces) {
				if (count($spaces['space']) == 0) {
					print "<div>No spaces were returned from the ScreenSteps Live server.</div>";
				} else {				
					// Print FORM and header
					print ('<form method="post" action="' . GETENV('REQUEST_URI') . '">' . "\n");
					print ('<input type="hidden" name="pages_submitted" value="1">' . "\n");
					print ('<table class="optiontable form-table">');
					print ('<tr>' . "\n");
					print ('<th scope="column" style="width:10px;">Page ID</th>' . "\n");
					print ('<th scope="column">Space</th>' . "\n");
					print ('<th scope="column">Manual</th>' . "\n");
					print ('</tr>' . "\n");
					
					$pages = get_option('screenstepslive_pages');
					$manauls = array();
					$buckets = array();
					
					if (is_array($pages) ) {
						foreach ($pages as $key => $page) {
							$i = $page['id'];
							print ('<tr>' . "\n");
								// Page id column
								print ('<td width="30px">');
									print ('<input type="hidden" name="pages[' . $i . '][id]" id="page_' . $i . '"' . 'value="'. $page['id'] . '"/>' . $page['id']);
								print ('</td>' . "\n");
								
								// Spaces select menu column
								if (count($spaces['space']) > 0) {
									print ('<td><select name="pages[' . $i . '][space_id]' . '">');
									foreach ($spaces['space'] as $key => $space) {
										// Determine initial state for visible checkbox and permission settings.
										if ($space['id'] == $page['space_id']) {
											print '<option value="' . $space['id'] . '" selected="selected">' . $space['title'] . '</option>';
										} else {
											print '<option value="' . $space['id'] . '">' . $space['title'] . '</option>';
										}
									}
									print('</select></td>' . "\n");
								} else {
									print ('<td>None</td>' . "\n");
								}						
								
								// Manual select menu column
								if ($page['space_id'] > 0) {
									if (!isset($manuals[ $page['space_id'] ])) {
										$space = $sslivewp->GetSpace($page['space_id']);
										foreach ($space['assets']['asset'] as $asset) {
											if (strtolower($asset['type']) == 'manual') {
												$manuals[ $page['space_id'] ][] = array('id'=>$asset['id'], 'title'=>$asset['title']);
											} elseif (strtolower($asset['type']) == 'bucket') {
												$buckets[ $page['space_id'] ][] = array('id'=>$asset['id'], 'title'=>$asset['title']);
											}
										}
									}
	
									if (count($manuals[ $page['space_id'] ]) > 0) {
										print ('<td><select name="pages[' . $i . '][resource_id]' . '">');
											print ('<option value="0">None</option>');
										foreach ($manuals[ $page['space_id'] ] as $manual) {
											// Determine initial state for visible checkbox and permission settings.
											if ($manual['id'] == $page['resource_id']) {
												print '<option value="' . $manual['id'] . '" selected="selected">' . $manual['title'] . '</option>';
											} else {
												print '<option value="' . $manual['id'] . '">' . $manual['title'] . '</option>';
											}
										}
										print('</select></td>' . "\n");
									} else {
										print ('<input type="hidden" name="pages[' . $i . '][resource_id]" value="0" />');
										print ('<td>No manuals in space</td>' . "\n");
									}		
	
								} else {
									print ('<input type="hidden" name="pages[' . $i . '][resource_id]" value="0" />');
									print ('<td>None</td>' . "\n");
								}
								
							print ('</tr>' . "\n");
						}
					}
					
						print ('<tr><td colspan="3">');
								print ('<p><a href="' . GETENV('REQUEST_URI') . '&ssliveaction=create_page">Create ScreenSteps Live Page</a></p>');
						print ('</td></tr>');
					
					print ("</table>\n");
		
		echo <<<END
					<div class="submit">
						<input type="submit" id="submit_page_settings" value="Save WordPress Page Settings"/>
					</div>
				</form>
END;
		
				}
			} else {
				print ("<div>Error:" . $sslivewp->last_error . "</div>\n");
			}
echo <<<END
	</fieldset>
	<br />
END;
}


function screenstepslive_createTemplatePage($type) {
		if (!current_user_can( 'edit_others_pages' )) {
			return new WP_Error( 'edit_others_pages', __( 'You are not allowed to create pages as this user.' ) );
		}
	
		$user = wp_get_current_user();
	
		$post['post_author'] = $user->id;
		$post['post_type'] = 'page';
		$post['post_status'] = 'draft';
		$post['comment_status'] = 'closed';
		$post['ping_status'] = 'closed';
		
		switch($type) {
			case 'spaces':
				$post['post_title'] = 'Spaces';
				$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}';
				break;
			
			case 'space':
				$post['post_title'] = '{{SCREENSTEPSLIVE_SPACE_TITLE}}';
				$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}' . "\n" .
										'<a href="{{SCREENSTEPSLIVE_LINK_TO_SPACES_INDEX}}">Return to spaces</a>';
				break;
				
			case 'manual':
				$post['post_title'] = '{{SCREENSTEPSLIVE_MANUAL_TITLE}}';
				$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}' . "\n" .
										'<a href="{{SCREENSTEPSLIVE_LINK_TO_SPACE}}">Return to space</a>';
				break;
			
			case 'bucket':
				$post['post_title'] = '{{SCREENSTEPSLIVE_BUCKET_TITLE}}';
				$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}' . "\n" .
										'<a href="{{SCREENSTEPSLIVE_LINK_TO_SPACE}}">Return to space</a>';
				break;
				
			case 'lesson':
				$post['post_title'] = '{{SCREENSTEPSLIVE_LESSON_TITLE}}';
				$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}' . "\n" .
										'{{SCREENSTEPSLIVE_LINK_TO_PREV_LESSON text="Previous Lesson: {{SCREENSTEPSLIVE_PREV_LESSON_TITLE}}"}}' . "\n" .
										'{{SCREENSTEPSLIVE_LINK_TO_NEXT_LESSON text="Next Lesson: {{SCREENSTEPSLIVE_NEXT_LESSON_TITLE}}"}}' . "\n" .
										'<a href="{{SCREENSTEPSLIVE_LINK_TO_MANUAL}}">Return to Manual</a>';
				break;
			case 'bucket lesson':
				$post['post_title'] = '{{SCREENSTEPSLIVE_LESSON_TITLE}}';
				$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}' . "\n" .
										'<a href="{{SCREENSTEPSLIVE_LINK_TO_BUCKET}}">Return to Lesson Bucket</a>';
				break;
				
			default:
				$post['post_title'] = 'ScreenSteps Live Page';
				$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}';
				break;
		}
		
		$postID = wp_insert_post($post);
		
		if (is_wp_error($postID))
			return $post_ID;
	
		if (empty($postID))
			return 0;
			
		return $postID;
	}

//echo <<<END
			
//END;

?>