<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ad_manager extends CI_Controller {

    private $user = null;


	public function __construct() {
		parent::__construct();
        $this->load->model("ad_model");
        $this->load->model("user_model");
        $this->disable_client_cache();
	}

	public function index()
	{
		$this->login_form();
	}

	public function version() {
	    echo SITE_VERSION;
    }

    /**
     * Shows login form and ends output to override any other function
     */
    public function login_form() {
        $this->user_model->logout();
	    die($this->load->view("manager/page_view",array("content_view"=>"manager/login_view","content_data"=>'', "hide_footer"=>true),true));
    }

    /**
     * Login action
     */
    public function login() {
        $this->user_model->login($this->input->post('username'), $this->input->post('password'));
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        redirect(site_url("ad_manager/main"));
    }

    /**
     * Logout action
     */
    public function logout() {
        $this->user_model->logout();
        redirect(site_url("ad_manager"));
    }

    /**
     * Admanager main view
     */

    public function main() {
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $this->show_main_page();
    }

    /**
     * Show the new ad form
     */
    public function new_ad() {
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $data = array();
        $data["profiles"] = $this->ad_model->get_profiles();
        $this->load->view("manager/page_view",array("content_view"=>"manager/new_ad_view","content_data"=>$data));

    }

    /**
     * Show the new profile form
     */
    public function new_profile() {
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $data = array();
        $data["ad"] = $this->ad_model->new_profile();
        $data["profiles"] = $this->ad_model->get_profiles();
        $data["htmlTemplates"] = $this->ad_model->get_templates();
        $data["cookie_dropping_methods"] = $this->get_cookie_dropping_methods();
        $this->load->view("manager/page_view",array("content_view"=>"manager/edit_ad_view","content_data"=>$data));
    }


    /**
     * Show the server settings view
     */
    public function view_server_settings() {
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $this->load->model("server_settings_model");
        $this->load->view("manager/server_settings_view",array("settings"=>$this->server_settings_model->get()));
    }

    /**
     * Save the server settings
     */
    public function save_server_settings() {
        $response["result"] = "error";
        $response["message"] = "Unknown error";
        if (!$this->user_model->is_authenticated()) {
            $response["message"] = "unauthorized";
            echo json_encode($response);
            return;
        }
        $this->load->model("server_settings_model");

        /**
         * @var server_settings
         */
        $settings = new server_settings();
        $settings->landing_js_referer_blacklist = array_filter(explode("|",$this->input->post("landing_js_referer_blacklist")));
        $settings->landing_js_referer_whitelist = array_filter(explode("|",$this->input->post("landing_js_referer_whitelist")));
        $settings->landing_page_referer_blacklist= array_filter(explode("|",$this->input->post("landing_page_referer_blacklist")));
        $settings->landing_page_referer_whitelist= array_filter(explode("|",$this->input->post("landing_page_referer_whitelist")));
        $settings->landing_page_cloak_redirect_url = $this->input->post("landing_page_cloak_redirect_url");
        $settings->cookies_dropping_delay = $this->input->post("cookies_dropping_delay");
        $settings->cookies_dropping_interval = $this->input->post("cookies_dropping_interval");
        $redirect_enabled = $this->input->post("landing_page_cloak_redirect_enabled");
        $settings->landing_page_cloak_redirect_enabled = !empty($redirect_enabled);
        $this->server_settings_model->save($settings);
        $response["result"] = "ok";
        $response["message"] = "Settings saved";
        echo json_encode($response);
    }


    /**
     * Show the edit form of an ad
     */
    public function edit($id) {
        $id = urldecode($id);
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $this->load->helper("array_helper");
        $data = array();
        $data["ad"] = $this->ad_model->get($id);
        $data["cookie_dropping_methods"] = $this->get_cookie_dropping_methods();
        $this->load->view("manager/page_view",array("content_view"=>"manager/edit_ad_view","content_data"=>$data));
    }

    /**
     * Show the edit form of an ad
     */
    public function edit_profile($id) {
        $id = urldecode($id);
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $this->load->helper("array_helper");
        $data = array();
        $data["ad"] = $this->ad_model->get_profile($id);
        $data["htmlTemplates"] = $this->ad_model->get_templates();
        $data["cookie_dropping_methods"] = $this->get_cookie_dropping_methods();
        $this->load->view("manager/page_view",array("content_view"=>"manager/edit_ad_view","content_data"=>$data));
    }

    /**
     * Copies an Ad to another
     */
    public function copy($id, $new_id) {
        $id = urldecode($id);
        $new_id = urldecode($new_id);
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $this->ad_model->copy($id, $new_id);
        $this->show_main_page("Copied");
    }

    /**
     * Copies a Profile to another
     */
    public function copy_profile($id, $new_id) {
        $id = urldecode($id);
        $new_id = urldecode($new_id);
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $this->ad_model->copy_profile($id, $new_id);
        $this->show_main_page("Copied");
    }

    /**
     * Creates a new Ad
     */
    public function insert_ad() {
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $this->insert();
    }

    /**
     * Deletes an Ad
     */
    public function delete($id) {
        $id = urldecode($id);
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $this->ad_model->delete($id);
        $this->show_main_page("Deleted");
    }

    /**
     * Deletes an Ad
     */
    public function delete_profile($id) {
        $id = urldecode($id);
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $this->ad_model->delete_profile($id);
        $this->show_main_page("Deleted");
    }

    /**
     * Show ad's log files
     */
    public function view_log($id, $log_type = "page") {
        /*error_reporting(E_ALL ^ E_WARNING);
        ini_set('display_errors', 1);*/
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $id = urldecode($id);
        $log_type = urldecode($log_type);
        if ($log_type=="page") {
            $this->show_log_page($id);
        } else if ($log_type=="stats") {
            $this->show_log_stats($id);
        } else {
            $this->show_log($id,$log_type);
        }
    }

    /**
     * View ad log (database version)
     */
    public function view_ad_log($campaign_id, $log_type) {
        $this->load->model("log_model");
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $id = urldecode($campaign_id);
        $log_type = urldecode($log_type);
        $data["campaign_id"] = $campaign_id;
        $data["log_type"] = $log_type;
        $data["log_name"] = $this->log_model->get_log_name($log_type);
        $data["log_rows"] = $this->log_model->query($campaign_id, $log_type);
        $this->load->view("manager/ad_log_view",$data);
    }

    /**
     * Download ad log (database version)
     */
    public function download_ad_log($campaign_id, $log_type) {
        $this->load->model("log_model");
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $id = urldecode($campaign_id);
        $log_type = urldecode($log_type);
        $rows = $this->log_model->query($campaign_id, $log_type,true);
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=file.csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        $out = fopen('php://output', 'w');
        fputcsv($out, array("Date","Client GUID","User Agent","IP","Port","ISP","Headers","Message"));
        foreach ($rows as $row) {
            $line = array($row->date_registered->format('Y-m-d H:i:s'),
                $row->client_guid,
                $row->user_agent,
                $row->remote_ip,
                $row->remote_port,
                $row->isp,
                $row->headers,
                $row->message);
            fputcsv($out, $line);
        }
        fclose($out);
    }

    public function reset_ad_log($log_type) {
        echo "Log reset started<br/>";
        $this->load->model("log_model");
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $log_type = urldecode($log_type);
        try {
            echo $this->log_model->reset_log($log_type);
        } catch (Exception $ex) {
            echo "Error: ";
            print_r($ex);
            echo "<br/>";
        }

        echo "Log reset ended<br/>";
    }

    /**
     * Delete ad log (database version)
     */
    public function delete_ad_log($campaign_id, $log_type) {
        $this->load->model("log_model");
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $id = urldecode($campaign_id);
        $log_type = urldecode($log_type);
        $rows = $this->log_model->clear_log($campaign_id, $log_type);
        die(json_encode(array("result"=>"ok")));
    }

    public function delete_log($id, $log_type) {
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $id = urldecode($id);
        $log_type = urldecode($log_type);
        $this->ad_model->delete_log($id,$log_type);
        die(json_encode(array("result"=>"ok")));
    }

    public function delete_logs($id) {
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $id = urldecode($id);
        $this->ad_model->delete_logs($id);
        die(json_encode(array("result"=>"ok")));
    }

    private function show_log_stats($id) {
        $stats = $this->ad_model->get_stats($id);
        $this->load->view("manager/stats_view",array("id" => $id, "stats"=>$stats));
    }

    private function show_log($id, $log_type) {
        $rows = $this->ad_model->get_log($id, $log_type);
        $this->load->view("manager/log_view",array("id" => $id, "rows"=>$rows, "log_type"=>$log_type));
    }

    private function show_log_page($id) {
        $data["id"] = $id;
        $data["logs"] = $this->ad_model->get_log_names();
        $this->load->view("manager/page_view",array("content_view"=>"manager/logs_view","content_data"=>$data));
    }

    private function insert() {

        $profile_id = str_replace(".profile","",$this->input->get_post("profile"));
        $profile = $this->ad_model->get_profile($profile_id);
        $configArray["HTMLTemplate"] = $profile->HTMLTemplate;
        $configArray["HTMLTemplateValues"] = json_encode($profile->HTMLTemplateValues);
        $this->ad_model->save_ad($_POST['campaignID'], $configArray);
        $this->show_main_page("Saved!");
    }
            /**
             * Updates an AD or profile
             */
    public function update() {
        if (!$this->user_model->is_authenticated()) {
            $this->login_form();
        }
        $configArray = array();
        $HTMLTemplateValues = array();
        foreach ($_POST as $key => $value)
        {
            if($key{0} === strtoupper($key{0}))
            {
                if (strpos($key, "HTMLTemplateValues_") !== false)
                {
                    if (is_array($value))
                    {
                        $HTMLTemplateValues[substr($key, strlen("HTMLTemplateValues_"))] = array_values(array_filter($value));
                    }
                    else
                    {
                        $HTMLTemplateValues[substr($key, strlen("HTMLTemplateValues_"))] = $value;
                    }
                }
                elseif (strpos($key, "PopunderTemplate") !== false) {
                    $configArray["PopunderTemplate"] = json_encode(array("html"=>$value));
                }
                elseif (is_array($value))
                {
                    $convertedValue = array();

                    if (strpos($key, "AffiliateLinkUrl") === false)
                    {
                        for ($i = 0; $i < sizeof($value); $i += 2)
                        {
                            if (!empty($value[$i]))
                            {
                                $convertedValue[$value[$i]] = explode("|", $value[$i + 1]);
                            }
                        }
                    } else {
                        // We will JSON encode the value as it is
                        for ($i = 0; $i < sizeof($value); $i ++)
                        {
                            if (!empty($value[$i]))
                            {
                                $convertedValue[$i] = $value[$i];
                            }
                        }
                    }
                    $configArray[$key] = json_encode($convertedValue);
                }
                else
                {
                    $configArray[$key] = $value;
                }
            }
        }

        if (empty($HTMLTemplateValues) && !empty($configArray["HTMLTemplate"]))
        {
            $HTMLTemplateValues = $this->ad_model->get_template_values($configArray["HTMLTemplate"]);
        }

        $configArray["HTMLTemplateValues"] = json_encode($HTMLTemplateValues);

        //print_r_nice($configArray);

        if (array_key_exists("cleanHtml", $_POST))
        {
            $this->ad_model->save_ad($_POST['campaignID'], $configArray,$_POST['cleanHtml']);
        }
        else
        {
            if (!empty($_POST['campaignID']))
            {
                $this->ad_model->save_ad($_POST['campaignID'], $configArray);
            }
            else
            {
                $this->ad_model->save_profile($_POST['profileName'], $configArray);
            }
        }
        $this->show_main_page("Saved!");
    }



    private function show_main_page($toast_success_message = "") {
        $data = array();
        $data["ads"] = $this->ad_model->get_list();
        $data["profiles"] = $this->ad_model->get_profiles();
        $this->load->view("manager/page_view",array("content_view"=>"manager/main_view","content_data"=>$data, "toast_success_message"=>$toast_success_message));
    }

	private function disable_client_cache() {
        header("Expires: Mon, 01 Jan 1985 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0, max-age=0", false);
        header("Pragma: no-cache");
    }

    private function set_output_to_js() {
        header("Content-Type: application/javascript");
    }

	private function show_not_found() {
		header("HTTP/1.1 404 Not Found");
		$this->load->view("errors/html/error_404",Array("heading"=>"Page not found","message"=>"The link to the page may be broken"));
	}

	private function get_cookie_dropping_methods() {
        return array(
            "Landing Page" => COOKIES_DROPPING_METHOD_LANDING_PAGE,
            "Popunder" => COOKIES_DROPPING_METHOD_POP_UNDER,
            "Cloaker" => COOKIES_DROPPING_METHOD_CLOAKER,
        );
    }
}


