<?php
if ( !isset( $_SESSION['user_id'] ) && !isset( $IGNORE_LOGIN ) ) {
    header("Location: index.php");
    exit();
}

if ( !isset( $pagetitle ) ) { $pagetitle='Approvals System'; }

/*
 * function makeMenuItem( $title, $tooltip, $url );
 * 
 * Inputs:
 *   $title   - The displayed name of the menu, e.g.: File
 *   $tooltip - The value that will be displayed on a mouseover
 *   $url     - The url of the page to be directed to
 * Outputs:
 *   An array containing the title, tooltip, and url of the item.
 *
 */
function makeMenuItem( $title, $tooltip, $url ) {
  return array( 
    'display' => $title,
    'tooltip' => $tooltip,
    'url'     => $url
  );
}

/*
 * function makeMenu( $name, $title, $tooltip );
 * 
 * Inputs:
 *   $name    - the designator of the menu for the javascript
 *   $title   - The displayed name of the menu, e.g.: File
 *   $tooltip - The value that will be displayed on a mouseover
 * Outputs:
 *   An array containing the name, title, tooltip, and an array for entering the sub-items
 *
 */
function makeMenu( $name, $title, $tooltip ) {
  return array( 
    'name'    => $name,
    'display' => $title,
    'tooltip' => $tooltip,
    'items'   => array()
  );
}

/*
 * function generateMenus()
 * 
 * Inputs:
 *
 * Outputs: An array of menus and sub menus based on the current logged in session
 *
 */
function generateMenus(){
  $menus = array();

  // General menu (always available for all users)
  $current_menu = makeMenu( 'general', 'General', 'General User Tools' );
  $current_menu['items'][] = makeMenuItem("Profile", "User Profile",  "UserDisplay.php?mode=edit");
  $menus[] = $current_menu;
  
  // Player menu (always available for all users)
  $current_menu = makeMenu("player", "Player", "Player Tools");
  $current_menu['items'][] = makeMenuItem("My Characters", "My Characters",  "ModifyCharacterList1.php");
  $current_menu['items'][] = makeMenuItem("My Applications",  "My Applications",  "MyApps.php");
  $current_menu['items'][] = makeMenuItem("Add Character", "Add Character",  "AddCharacter.php");
  $current_menu['items'][] = makeMenuItem("Add Application", "Enter an new Application",  "AddNewApp.php");
  $current_menu['items'][] = makeMenuItem("Venue Style Sheet List", "Venue Style Sheet List",  "ModifyVSSList1.php");
  $current_menu['items'][] = makeMenuItem("Published Mechanics", "Published Mechanics",  "MechanicsMain.php");
  $menus[] = $current_menu;

  // Plotkits menu (only for admins with org/vss access)
  if ( count($_SESSION["admin_org_list"]) > 0 || count( $_SESSION["admin_vss_list"] ) > 0) { 
    $plotkits_menu = makeMenu("plotkits", "Plotkits", "Plotkit Menu");
    $plotkits_menu['items'][] = makeMenuItem("View Plotkits", "View Plotkits",  "PlotkitsMain.php");
    $plotkits_menu['items'][] = makeMenuItem("Submit Plotkit", "Submit Plotkit",  "PlotkitsSubmit.php");
    $menus[] = $plotkits_menu;
  }
  
  // Storyteller menu (only for admins with org/vss access)
  if ( count($_SESSION["admin_org_list"]) > 0 || count( $_SESSION["admin_vss_list"] ) > 0) {
    $st_menu = makeMenu("storyteller", "Storyteller", "Storyteller Tools");
    $st_menu['items'][] = makeMenuItem("Application Listing", "Application Listing",  "app_main.php");
    $st_menu['items'][] = makeMenuItem("User List", "User List",  "UserList.php");
    $st_menu['items'][] = makeMenuItem("Org List", "Org List",  "ModifyOrgList1.php");
    $st_menu['items'][] = makeMenuItem("Character Census", "Character Census",  "CharacterCensus.php");
    $st_menu['items'][] = makeMenuItem("Add NPC", "Add NPC", "AddNPC.php");
    $st_menu['items'][] = makeMenuItem("NPC List", "NPC List", "ModifyNPCList1.php");
    $st_menu['items'][] = makeMenuItem("Verify Approval", "Verify Approval", "VerifyApproval.php");
    $st_menu['items'][] = makeMenuItem("Storyteller Instructions", "Storyteller Instructions",  "ModifySTInstructions0.php");
    $st_menu['items'][] = makeMenuItem("VSS/Org Character List", "List the characters associated with the VSSs or Organizations you are the storyteller for.",  "ModifyVSSCharacterList1.php");
    $menus[] = $st_menu;
  }

  // Admin menu (only for super users)
  if ( $_SESSION["super_user"] ) {
    $admin_menu=makeMenu("admin", "Admin", "Administrative Tools");
    $admin_menu['items'][] = makeMenuItem("Venue List", "Venue List",  "ModifyVenueList1.php");
    $admin_menu['items'][] = makeMenuItem("Category List", "Category List",  "ModifyCategoryList1.php");
//    $admin_menu['items'][] = makeMenuItem("Admin DB Interface", "Custom Query", "AdministrativeDBChangeCustomQuery.php");
    $menus[] = $admin_menu;
  }

/*  // Help menu (commented out)
  $current_menu=makeMenu("help", "Help", "Help");
  $current_menu['items'][] = makeMenuItem("FAQ", "Frequently Asked Questions",  "FaqDetails.php");
  $current_menu['items'][] = makeMenuItem("Feedback", "Submit Error Reports/Improvement Suggestions", "UserFeedback.php" );
  $current_menu['items'][] = makeMenuItem("About the IAS", "About the International Approvals System",  "Help.php");
*/
  
  // Ensure at least one menu exists to prevent broken page
  if (count($menus) === 0) {
    $default_menu = makeMenu("general", "General", "General User Tools");
    $default_menu['items'][] = makeMenuItem("Profile", "User Profile",  "UserDisplay.php?mode=edit");
    $menus[] = $default_menu;
  }
  
  return $menus;
}

$menus = generateMenus();