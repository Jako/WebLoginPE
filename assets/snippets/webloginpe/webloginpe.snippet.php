<?php
/**
 * WebLoginPE Snippet 1.4.0
 * v1.4.0 Bugfix by Soshite @ MODx CMS Forums & Various Other Forum Members
 *
 * @package WebLoginPE
 * @author Scotty Delicious
 * @author Soshite & various other MODX forum members
 * @author Thomas Jakobi
 * @version 1.4.0
 *
 * See the "docs" folder for detailed usage and parameter instructions.
 */

// set snippet (base) path
define('WLPE_PATH', str_replace(MODX_BASE_PATH, '', str_replace('\\', '/', realpath(dirname(__FILE__)))) . '/');
define('WLPE_BASE_PATH', MODX_BASE_PATH . WLPE_PATH);

$type = isset($type) ? $type : 'simple';
$regType = isset($regType) ? $regType : 'instant';
$notify = isset($notify) ? $notify : '';
$groups = isset($groups) ? $groups : '';
$groupsField = isset($groupsField) ? $groupsField : '';
$approvedDomains = isset($approvedDomains) ? $approvedDomains : '';
$pendingGroups = isset($pendingGroups) ? $pendingGroups : 'Pending Users';
$regRequired = isset($regRequired) ? $regRequired : '';
$customTable = isset($customTable) ? $customTable : 'web_user_attributes_extended';
$customFields = isset($customFields) ? $customFields : '';
$prefixTable = isset($prefixTable) ? $prefixTable : 1;
$lang = isset($lang) ? $lang : 'en';
$userImageSettings = isset($userImage) ? $userImage : '105000,100,100';
$dateFormat = isset($dateFormat) ? $dateFormat : '%A %B %d, %Y at %I:%M %p';
$dobFormat = isset($dobFormat) ? $dobFormat : '%m-%d-%Y'; // add by Bruno
$disableServices = isset($disableServices) ? explode(',', str_replace(', ', ',', $disableServices)) : array();
$tableCheck = isset($tableCheck) ? $tableCheck : 1;
$paging = isset($paging) ? $paging : 3000;
$templates = isset($templates) ? $templates : 'default';

if (!class_exists('WebLoginPE')) {
    include WLPE_BASE_PATH . 'classes/webloginpe.class.php';
}

if (file_exists(WLPE_BASE_PATH . 'webloginpe.templates.' . $templates . '.php')) {
    include WLPE_BASE_PATH . 'webloginpe.templates.' . $templates . '.php';
} else {
    include WLPE_BASE_PATH . 'webloginpe.templates.default.php';
}

$wlpe_lang = array();
include WLPE_BASE_PATH . 'lang/en.php';
if (file_exists(WLPE_BASE_PATH . 'lang/' . $lang . '.php')) {
    include WLPE_BASE_PATH . 'lang/' . $lang . '.php';
} else {
    $modx->setPlaceholder('wlpe.message', $wlpe_lang['error_missing_language_file']);
    echo '[+wlpe.message+]';
}

$wlpe = new WebLoginPE($modx, array(
    'type' => $type,
    'language' => $wlpe_lang,
    'userImageSettings'=>$userImageSettings,
    'dateFormat' =>$dateFormat,
    'paging' => $paging,
    'templateFolder' => $templates,
    'dobFormat' => $dobFormat
));
$wlpe->CustomTable($customTable, $customFields, $prefixTable, $tableCheck);

$liHomeId = isset($liHomeId) ? explode(',', $liHomeId) : '';
$loHomeId = isset($loHomeId) ? $loHomeId : '';
$regHomeId = isset($regHomeId) ? $regHomeId : '';
$regSuccessId = isset($regSuccessId) ? $regSuccessId : '';
$regSuccessPause = isset($regSuccessPause) ? $regSuccessPause : 5;
$profileHomeId = isset($profileHomeId) ? $profileHomeId : '';
$inputHandler = isset($inputHandler) ? explode('||', $inputHandler) : array();
$usersList = isset($usersList) ? $usersList : '';

$activateId = isset($activateId) ? $activateId : $modx->documentIdentifier;
$activateConfig = isset($activateConfig) ? $activateConfig : '';
$activatePost = isset($activatePost) ? $activatePost : '';

if ($regType == 'verify') {
    $wlpeRegisterTpl = $wlpeRegisterVerifyTpl;
} else {
    $wlpeRegisterTpl = $wlpeRegisterInstantTpl;
}

$displayLoginFormTpl = isset($loginFormTpl) ? $wlpe->Template($loginFormTpl) : $wlpeDefaultFormTpl;
$displaySuccessTpl = isset($successTpl) ? $wlpe->Template($successTpl) : $wlpeDefaultSuccessTpl;
$displayRegisterTpl = isset($registerTpl) ? $wlpe->Template($registerTpl) : $wlpeRegisterTpl;
$displayRegSuccessTpl = isset($registerSuccessTpl) ? $wlpe->Template($registerSuccessTpl) : $wlpeDefaultFormTpl;
$displayProfileTpl = isset($profileTpl) ? $wlpe->Template($profileTpl) : $wlpeProfileTpl;
$displayViewProfileTpl = isset($viewProfileTpl) ? $wlpe->Template($viewProfileTpl) : $wlpeViewProfileTpl;
$displayUsersOuterTpl = isset($usersOuterTpl) ? $wlpe->Template($usersOuterTpl) : $wlpeUsersOuterTpl;
$displayUsersTpl = isset($usersTpl) ? $wlpe->Template($usersTpl) : $wlpeUsersTpl;
$displayManageOuterTpl = isset($manageOuterTpl) ? $wlpe->Template($manageOuterTpl) : $wlpeUsersOuterTpl;
$displayManageTpl = isset($manageTpl) ? $wlpe->Template($manageTpl) : $wlpeManageTpl;
$displayManageProfileTpl = isset($manageProfileTpl) ? $wlpe->Template($manageProfileTpl) : $wlpeManageProfileTpl;
$displayManageDeleteTpl = isset($manageDeleteTpl) ? $wlpe->Template($manageDeleteTpl) : $wlpeManageDeleteTpl;
$displayProfileDeleteTpl = isset($profileDeleteTpl) ? $wlpe->Template($profileDeleteTpl) : $wlpeProfileDeleteTpl;
$displayActivateTpl = isset($activateTpl) ? $wlpe->Template($activateTpl) : $wlpeActivateTpl;
$displayResetTpl = isset($resetTpl) ? $wlpe->Template($resetTpl) : $wlpeResetTpl;
$notifyTpl = isset($notifyTpl) ? $wlpe->Template($notifyTpl) : $wlpeNotifyTpl;
$notifySubject = isset($notifySubject) ? $notifySubject : 'New Web User for ' . $modx->config['site_name'] . '.';
$messageTpl = isset($messageTpl) ? $wlpe->Template($messageTpl) : $wlpeMessageTpl;
$tosChunk = isset($tosChunk) ? $wlpe->Template($tosChunk) : $wlpeTos;
$wlpe->setPlaceholder('tos', $tosChunk);

$loadJquery = isset($loadJquery) ? $loadJquery : false;
$loadBtnfix = isset($loadBtnfix) ? $loadBtnfix : false;
$customJs = isset($customJs) ? $customJs : '';

if (isset($pruneDays)) {
    $wlpe->PruneUsers($pruneDays);
}

if ($loadJquery == 'true' || $loadJquery == true || $loadJquery == 1 || $loadJquery == '1') {
    $wlpe->RegisterScripts($customJs);
} else if (!empty($customJs)) {
    $modx->regClientStartupScript($customJs);
}

$wlpe->ActiveUsers();
$wlpe->PlaceHolders($inputHandler, $messageTpl);

if ($loadBtnfix) {
    $modx->regClientStartupScript('<!--[if lt IE 8]><script src="assets/snippets/webloginpe/js/btnfix.js"></script><![endif]-->');
}

$service = $_REQUEST['service'];
if (empty($service) || $service == '') {
    $service = $_REQUEST['serviceButtonValue'];
}

if ($type == 'register') {
    if (in_array('register', $disableServices)) {
        return;
    }
    switch ($service) {
        case 'register' :
            if (in_array('register', $disableServices)) {
                return;
            }
            $registration = $wlpe->Register($regType, $groups, $regRequired, $notify, $notifyTpl, $notifySubject, $approvedDomains, $pendingGroups);

            if (isset($regSuccessId) && $regSuccessId !== '') {
                if ($registration == 'success') {
                    $url = $modx->makeURL($regSuccessId);
                    $modx->sendRedirect($url, $regSuccessPause, 'REDIRECT_REFRESH');
                    //header('Refresh: '.$regSuccessPause.';URL='.$url);
                    return $wlpe->replacePlaceholder($displayRegSuccessTpl);
                }
                return $wlpe->replacePlaceholder($displayRegisterTpl);

            }
            if ($registration == 'success') {
                return $wlpe->replacePlaceholder($displayRegSuccessTpl);
            }
            return $wlpe->replacePlaceholder($displayRegisterTpl);
            break;

        case 'cancel':
            if ($loHomeId == '') $loHomeId = $modx->config['site_start'];
            $url = $modx->makeURL($loHomeId);
            $modx->sendRedirect($url, 0, 'REDIRECT_REFRESH');
            break;

        case 'login' :
            $wlpe->Login($type, $liHomeId);

            if ($modx->getLoginUserID()) {
                return $wlpe->replacePlaceholder($displaySuccessTpl);
            }
            return $wlpe->replacePlaceholder($displayLoginFormTpl);
            break;

        case 'logout' :
            $wlpe->Logout($type, $loHomeId);
            return $wlpe->replacePlaceholder($displayLoginFormTpl);
            break;

        default :
            return $wlpe->replacePlaceholder($displayRegisterTpl);
    }
    return;
} else if ($type == 'profile') {
    if (in_array('profile', $disableServices)) {
        return;
    }
    switch ($service) {
        case 'saveprofile' :
            if (in_array('saveprofile', $disableServices)) {
                return;
            }
            $wlpe->SaveUserProfile();
            $wlpe->PlaceHolders($inputHandler, $messageTpl);
            return $wlpe->replacePlaceholder($displayProfileTpl);
            break;

        case 'cancel':
            if ($loHomeId == '') $loHomeId = $modx->config['site_start'];
            $url = $modx->makeURL($loHomeId);
            $modx->sendRedirect($url, 0, 'REDIRECT_REFRESH');
            break;

        case 'logout':
            if ($loHomeId == '') $loHomeId = $modx->config['site_start'];
            $wlpe->Logout($type, $loHomeId);
            break;

        case 'deleteprofile':
            if (in_array('deleteprofile', $disableServices)) {
                return;
            }
            return $wlpe->replacePlaceholder($displayProfileDeleteTpl);
            break;

        case 'confirmdeleteprofile':
            if (in_array('confirmdeleteprofile', $disableServices)) {
                return;
            }
            $wlpe->RemoveUserProfile();
            return '[+wlpe.message+]';
            break;

        default :
            return $wlpe->replacePlaceholder($displayProfileTpl);
            break;
    }
    return;
} else if ($type == 'users') {
    if (in_array('users', $disableServices)) {
        return;
    }
    switch ($service) {
        case 'viewprofile':
            if (in_array('viewprofile', $disableServices)) {
                return;
            }
            $wlpe->ViewUserProfile($_REQUEST['username'], $inputHandler);
            return $wlpe->replacePlaceholder($displayViewProfileTpl);
            break;

        case 'messageuser':
            if (in_array('messageuser', $disableServices)) {
                return;
            }
            $wlpe->SendMessageToUser();
            return $wlpe->replacePlaceholder($displayViewProfileTpl);
            break;

        default :
            $userpage = $wlpe->ViewAllUsers($displayUsersTpl, $displayUsersOuterTpl, $usersList);
            return $userpage;
    }
    return;
} else if ($type == 'manager') {
    if (in_array('manager', $disableServices)) {
        return;
    }
    switch ($service) {
        case 'editprofile':
            if (in_array('editprofile', $disableServices)) {
                return;
            }
            $wlpe->ViewUserProfile($_REQUEST['username'], $inputHandler);
            return $wlpe->replacePlaceholder($displayManageProfileTpl);
            break;

        case 'saveuserprofile' :
            if (in_array('saveuserprofile', $disableServices)) {
                return;
            }
            // Added to allow setting the groups via a form
            if (!empty($_REQUEST[$groupsField])) {
                if (is_array($_REQUEST[$groupsField])) {
                    $groups = implode(",", $_REQUEST[$groupsField]);
                } else {
                    $groups = $_REQUEST[$groupsField];
                }
            }
            $wlpe->SaveUserProfile($_POST['internalKey'], $groups);
            $manageUsersPage = $wlpe->ViewAllUsers($displayManageTpl, $displayManageOuterTpl, $usersList);
            return $manageUsersPage;
            break;

        case 'approveuser' :
            if (in_array('approveuser', $disableServices)) {
                return;
            }
            // Added to allow setting the groups via a form
            if (!empty($_REQUEST[$groupsField])) {
                $groups = $_REQUEST[$groupsField];
            }
            $activate = true;
            $wlpe->SaveUserProfile($_POST['internalKey'], $groups, $activate, $activateId, $activateConfig, $activatePost);
            $manageUsersPage = $wlpe->ViewAllUsers($displayManageTpl, $displayManageOuterTpl, $usersList);
            return $manageUsersPage;
            break;

        case 'messageuser':
            if (in_array('messageuser', $disableServices)) {
                return;
            }
            $wlpe->SendMessageToUser();
            return $wlpe->replacePlaceholder($displayViewProfileTpl);
            break;

        case 'deleteuser':
            if (in_array('deleteuser', $disableServices)) {
                return;
            }
            $_SESSION['editInternalKey'] = $_POST['internalKey'];
            return $wlpe->replacePlaceholder($displayManageDeleteTpl);
            break;

        case 'confirmdeleteuser':
            if (in_array('confirmdeleteuser', $disableServices)) {
                return;
            }
            $wlpe->RemoveUserProfileManager($_SESSION['editInternalKey']);
            $manageUsersPage = $wlpe->ViewAllUsers($displayManageTpl, $displayManageOuterTpl, $usersList);
            unset($_SESSION['editInternalKey']);
            return $manageUsersPage;
            break;

        default :
            $manageUsersPage = $wlpe->ViewAllUsers($displayManageTpl, $displayManageOuterTpl, $usersList);
            return $manageUsersPage;
    }
    return;
} else if ($type == 'simple') {
    switch ($service) {

        case 'login' :
            $wlpe->Login($type, $liHomeId);

            if ($modx->getLoginUserID()) {
                return $wlpe->replacePlaceholder($displaySuccessTpl);
            }
            return $wlpe->replacePlaceholder($displayLoginFormTpl);
            break;

        case 'logout' :
            $wlpe->Logout($type, $loHomeId);
            return $wlpe->replacePlaceholder($displayLoginFormTpl);
            break;

        case 'profile' :
            if (in_array('profile', $disableServices)) {
                return;
            }
            if (empty($profileHomeId)) {
                return $wlpe->replacePlaceholder($displayProfileTpl);
            }
            $url = $modx->makeURL($profileHomeId);
            $modx->sendRedirect($url, 0, 'REDIRECT_REFRESH');
            return;
            break;

        case 'saveprofile' :
            if (in_array('saveprofile', $disableServices)) {
                return;
            }
            $wlpe->SaveUserProfile();
            $wlpe->PlaceHolders($inputHandler, $messageTpl);
            return $wlpe->replacePlaceholder($displayProfileTpl);
            break;

        case 'deleteprofile':
            if (in_array('deleteprofile', $disableServices)) {
                return;
            }
            return $wlpe->replacePlaceholder($displayProfileDeleteTpl);
            break;

        case 'confirmdeleteprofile':
            if (in_array('confirmdeleteprofile', $disableServices)) {
                return;
            }
            $wlpe->RemoveUserProfile();
            return '[+wlpe.message+]';
            break;

        case 'registernew' :
            if (in_array('register', $disableServices)) {
                return;
            }
            if (empty($regHomeId)) {
                return $wlpe->replacePlaceholder($displayRegisterTpl);
            }
            $url = $modx->makeURL($regHomeId);
            $modx->sendRedirect($url, 0, 'REDIRECT_REFRESH');
            return;
            break;

        case 'register':
            if (in_array('register', $disableServices)) {
                return;
            }
            $registration = $wlpe->Register($regType, $groups, $regRequired, $notify, $notifyTpl, $notifySubject);

            if (isset($regSuccessId) && $regSuccessId !== '') {
                if ($registration == 'success') {
                    $url = $modx->makeURL($regSuccessId);
                    $modx->sendRedirect($url, $regSuccessPause, 'REDIRECT_REFRESH');
                    //header('Refresh: '.$regSuccessPause.';URL='.$url);
                    return $wlpe->replacePlaceholder($displayRegSuccessTpl);
                }
                return $wlpe->replacePlaceholder($displayRegisterTpl);

            }
            if ($registration == 'success') {
                return $wlpe->replacePlaceholder($displayRegSuccessTpl);
            }
            return $wlpe->replacePlaceholder($displayRegisterTpl);
            break;

        case 'forgot' :
            if (in_array('forgot', $disableServices)) {
                return;
            }
            return $wlpe->replacePlaceholder($displayResetTpl);
            break;

        case 'resetpassword' :
            if (in_array('resetpassword', $disableServices)) {
                return;
            }
            $wlpe->ResetPassword();
            if (isset($wlpe->Report)) {
                if (isset($_POST['email'])) {
                    return $wlpe->replacePlaceholder($displayResetTpl);
                } else {
                    return $wlpe->replacePlaceholder($displayActivateTpl);
                }
            }
            return;
            break;

        case 'activate' :
            if (in_array('activate', $disableServices)) {
                return;
            }
            return $wlpe->replacePlaceholder($displayActivateTpl);
            break;

        case 'activated':
            if (in_array('activated', $disableServices)) {
                return;
            }
            $wlpe->ActivateUser();
            // pixelchutes 1:57 AM 9/19/2007
            // Here we check for an error, then reload the activation template if necessary
            // Do NOT reload if wlpe->Report indicates success
            // Added strip_tags() around string which means an error is not thrown regarding a modifier from closing
            // html tag e.g. if $wlpe_lang['message_password_activated'] contains "</div>" this will fail as "/d" treated as modifier
            if (isset($wlpe->Report) && !preg_match("/" . strip_tags($wlpe_lang['message_password_activated']) . "/i", $wlpe->Report)) {
                return $wlpe->replacePlaceholder($displayActivateTpl);
            }
            return $wlpe->replacePlaceholder($displayLoginFormTpl);
            break;

        default :

            if ($modx->getLoginUserID()) {
                return $wlpe->replacePlaceholder($displaySuccessTpl);
            } else {
                $wlpe->AutoLogin();
                return $wlpe->replacePlaceholder($displayLoginFormTpl);
            }

    }
    // [END] Switch : $service for simple.
} else if ($type == 'taconite') {
    switch ($service) {

        case 'login' :
            $wlpe->Login($type, $liHomeId);

            if (isset($wlpe->Report)) {
                return $wlpe->Report;
            }
            return;
            break;

        case 'logout' :
            $wlpe->Logout($type, $loHomeId);
            return;
            break;

        case 'register' :
            if (in_array('register', $disableServices)) {
                return;
            }
            $wlpe->Register($regType, $groups, $regRequired, $notify, $notifyTpl, $notifySubject);
            return $wlpe->Report;
            break;

        case 'resetpassword' :
            if (in_array('resetpassword', $disableServices)) {
                return;
            }
            $wlpe->ResetPassword();
            return $wlpe->Report;
            break;

        case 'activated':
            if (in_array('activated', $disableServices)) {
                return;
            }
            $wlpe->ActivateUser();
            return $wlpe->Report;
            break;

        default :
            if ($modx->getLoginUserID()) {
                return;
            } else {
                $wlpe->AutoLogin();
            }
    }
    // [END] Switch : $service for taconite.
} else {
    return;
}
?>