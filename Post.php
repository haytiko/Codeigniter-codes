<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Post extends CI_Controller{

    private $check_previous_login;
    private $inserted_user_id;
    
    function __construct(){
        parent::__construct();
    }

    public function index($hotel_name)
    {
        if (!empty($_POST)) {
                        
            $login = $_POST['LOGIN'];
            
            //Convert postmans' timestamp to mysql's datetime
            $login = date('Y-m-d H:i:s', $login);
            
            if(empty($_POST['Email'])) {
                $email = $_POST['email'];
            } else {
                $email = $_POST['Email'];
            }
            
            $room_number = $_POST['room_number'];
            $hotspot_id = $_POST['HOTSPOT_ID'];
            $mac_address = $_POST['MAC_ADDRESS'];
            $browser = $_POST['BROWSER'];

            //Insert new registrated user
                        
            $this->load->model('users');
            $this->load->model('emailstatus');
            
            $data = [
                'login' => $login,
                'room_number' => $room_number,
                'email' => $email,
                'hotspot_id' => $hotspot_id,
                'mac_address' => $mac_address,
                'browser' => $browser
            ];

            //Check if user have already logined             
            if($this->users->ifUserExists($data)) {
                
                //Check previous login time of this user
                $this->check_previous_login = $this->users->checkPreviousLogin($data);                            

                $this->inserted_user_id = $this->users->updateExistedUser($data);
                
            } else {
                $this->inserted_user_id = $this->users->insertNewUser($data);
                //User doesn't exist so we can send him email
                $this->check_previous_login = true;
                //We need to add email with status (0 - added) into the email_status db                    
                $this->emailstatus->setStatusSent($this->inserted_user_id);
            }            
                        
            if ($this->inserted_user_id) {                
                // So we need to add pair hotspot_id/hotel_name in hotspot_hotel table                
                $this->load->model('hotels');
                //Insert new pare into the hotspot_hotel table only if they doesn't exist yet
                $this->hotels->addHotspotHotelPair($hotspot_id, urldecode($hotel_name));
                
                //Now we need add this use email to the elastic list with the same name as Hotel_name
                $this->addContactToElastic($email, $hotel_name);
                
                //Now we have to send email to this new added user via Elastic
                //Check if last login was 2 weeks ago then allow to send email again
                if($this->check_previous_login) {                    
                    $result = $this->sendElasticEmail($email, $hotel_name);
                    $result_to_lower = 'E'.strtolower($result);
                    if(strpos($result_to_lower, 'error')) {
                        // if response contains an error
                        echo $result;
                    } else {
                        //Change the status of email from added (0) to sent (1)
                        $this->emailstatus->updateStatusSent($this->inserted_user_id);
                        echo 'Complete insert';
                    }      
                } else {
                    echo 'User last login was less than 2 weeks ago';
                }                                                          
            }
        }
    }

    public function addContactToElastic($email, $list_name)
    {
        
        $this->load->helper('url');
                
        // Elastic URL parameters
        $publicaccountid      = '099506b8-dd6a-4d35-b279-01c3befeeee6';
        $elastic_email        = $email;
        $elastic_listname     = $list_name;
        $elastic_title        = 'Notification email';
        
        // get URL for the Elastic Email Add Contact into the contact list
        $elastic_add_contact_url = "https://api.elasticemail.com/v2/contact/add?".
                                    "publicaccountid=".$publicaccountid.
                                    "&email=".$elastic_email.
                                    "&listname=".$elastic_listname.
                                    "&requiresactivation=false".
                                    "&title=".$elastic_title."/format/json";        
                
        $client = new GuzzleHttp\Client();
        
        // Send a request
        $response = $client->get($elastic_add_contact_url);

        // Get request body
        $result = (string) $response->getBody();
                
        return $result;
        
    }
    
    public function sendElasticEmail($to, $template_name)
    {      
        $data = "username=".urlencode("neo.matrix.sba@gmail.com");
        $data .= "&api_key=".urlencode("3306af7f-2cf6-4e19-8fcd-d10eb9fd26f0");
//        $data .= "&from=".urlencode("admin@admin.com");
//        $data .= "&from_name=".urlencode("Administration");
        $data .= "&to=".urlencode($to);
//        $data .= "&subject=".urlencode('subject');
        $data .= "&template=".urlencode($template_name." Welcome");
        
        // get URL for the Elastic Email Add Contact into the contact list
        $elastic_send_email = "https://api.elasticemail.com/mailer/send?".$data;
        
         $client = new GuzzleHttp\Client();
        
        // Send a request
        $response = $client->get($elastic_send_email);

        // Get request body
        $result = (string) $response->getBody();                

        return $result;               
    }

}