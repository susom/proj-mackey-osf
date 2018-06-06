<?php
namespace Stanford\OSF;
/** @var \Stanford\OSF\OSF $module */


use \Browser as Browser;
use \HtmlPage as HtmlPage;


/**
 * This is the configuration page for saving parameters as a json object
 * into this redcap module instance.
 *
 * Currently we are using a single json object named "module-name-config" to
 * store everything
 */

// Determine the context of this page:
global $project_id;
$context = $project_id ? "project" : "system";

if (!empty($_POST['action'])) {
	$action = $_POST['action'];

	switch ($action) {
		case "save":
			// SAVE A CONFIGURATION - THIS IS AN AJAX METHOD
            $raw_config = $_POST['raw_config'];

            // $module::log(empty($raw_config), "[" . $raw_config. "]", $_POST);
			// Validate that $raw is valid json if not empty!
            if(empty($raw_config)) {
                // Empty
                $module->setConfigAsString($raw_config);
                $result = array('result' => 'success');
            } else {
                // verify valid json
                json_decode($raw_config);
                $json_error = json_last_error_msg();
                if ($json_error !== "No error") {
                    $result = array(
                        'result' => 'error',
                        'message' => $json_error
                    );
                } else {
                    // Valid json
                    // SAVE
                    $module->setConfigAsString($raw_config);
                    $result = array('result' => 'success');
                }
            }

			header('Content-Type: application/json');
			print json_encode($result);
			exit();
			break;
		default:
			$module::log($_POST, "Unknown Action in Save");
			print "Unknown action";
	}
}


// Render the editor
$b = new Browser();
$cmdKey = ( $b->getPlatform() == "Apple" ? "&#8984;" : "Ctrl" );

// Initialize the Page
if ($context == "project") {
    $panel_title = $module->getModuleName() . " Configuration for Project " . $project_id;
    require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
} else {
    $panel_title = $module->getModuleName() . " System Configuration";
    $objHtmlPage = new HtmlPage();
    $objHtmlPage->addExternalJS(APP_PATH_JS . "base.js");
    $objHtmlPage->addStylesheet("jquery-ui.min.css", 'screen,print');
    $objHtmlPage->addStylesheet("style.css", 'screen,print');
    $objHtmlPage->addStylesheet("home.css", 'screen,print');
    $objHtmlPage->PrintHeader();
}

?>

    <div class="panel panel-primary">
        <div class="panel-heading"><strong><?php echo $panel_title ?></strong></div>
        <div class="panel-body"><?php echo (method_exists($module, "getConfigDirections") ? $module->getConfigDirections() : ""); ?></div>
    </div>

    <div class="panel panel-default">
<!--        <div class="panel-heading">-->
<!--            <strong>--><?php //echo $panel_title ?><!--</strong>-->
<!--        </div>-->
        <div class="panel-body config-editor">
            <div id='config_editor' data-editor="ace" data-mode="json" data-theme="clouds"></div>
        </div>
        <div class="panel-footer">
            <div class="config-editor-buttons">
                <button class="btn btn-primary" name="save">SAVE (<?php echo $cmdKey; ?>-S)</button>
                <button class="btn btn-default" name="beautify">BEAUTIFY</button>
                <button class="btn btn-default" name="cancel">CANCEL</button>
            </div>
        </div>
    </div>

	<style>
        #pagecontent { margin-top: 5px; }
		.config-editor { border-bottom: 1px solid #ddd; padding:0;}
	</style>

<?php

// Footer
if ($context == "project") {
    require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
} else {
    $objHtmlPage->PrintFooter();
}
?>
	<script src="<?php echo $module->getUrl('js/ace/ace.js'); ?>"></script>
    <script src="<?php echo $module->getUrl('js/config_editor.js'); ?>"></script>
    <script>
        // Set the value of the editor
        EM.startVal = <?php print json_encode( $module->getConfigAsString() ) ?>;
        EM.editor.instance.setValue(EM.startVal,1);
    </script>
<?php
