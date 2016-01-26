<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User extends CI_Controller
{
    
    public function index()
    {               

        if (isset($_SESSION['login'])) {                                 
            //Load Hotels model for getting all hotels and releted hotspots
            $this->load->model('hotels');
            
            // Get list of all hotels and hotspots
            $data['hotels'] = $this->hotels->getHotspotHotelPairs();
            
            // Load view with table of Hotels and Hotspot_IDs
            $this->load->view('user_page', $data);
        } else {
            $this->load->view('login_page');
        }
    }    

    public function simulate()
    {
        //AJAX also can help
        
        $client = new GuzzleHttp\Client();
        
        // Send a request
        $response = $client->post('http://www.hotspot.dev/post', [
            'body' => [
                'LOGIN' => 46546541321,
                'Email' => 'my_email1',
                'HOTSPOT_ID' => 'my_HOTSPOT_ID1',
                'MAC_ADDRESS' => 'my_MAC_ADDRESS1',
                'BROWSER' => 'my_BROWSER1'
            ]
        ]);

        // Get request body
        $result = (string) $response->getBody();
                
//        var_dump($result);
        return $result;
    }
    
    public function hotels()
    {
        if ($this->input->post('addHotelNameInput')) {
            $this->addHotel();
        }
        
        // Get all hotels
        $this->load->model('hotels');
        $data['hotels'] = $this->hotels->getHotels();

        $this->load->view('hotels', $data);      
    }
    
    public function addHotel()
    {
        if ($this->input->post('addHotelNameInput')) {
            // Get new added hotel name from POST
            $hotel_name = $this->input->post('addHotelNameInput');
        }
        
        if($this->addNewHotelToDb($hotel_name)) {            
            //Creating new Contact list into Elastic with current hotel name 
            $result_addNewElasticList = $this->addNewElasticList($hotel_name);

            if($result_addNewElasticList->success) {                
                //Creating new Template for new Hotel
                $result_createTemplateElasatic = $this->createTemplateElasatic($hotel_name);
                
                if(!$result_createTemplateElasatic->success) {
                    echo '<h5 style="text-align:center; color:red;">'. $result_createTemplateElasatic->error .'</h5>';
                }
            } else {
                echo '<h5 style="text-align:center; color:red;">'. $result_addNewElasticList->error .'</h5>';
            }
        }        
        
    }
    
    public function addNewHotelToDb($hotelName)
    {
        $hotel_name = $hotelName;
        
        $this->load->model('hotels');
        
        return $this->hotels->addNewHotel($hotel_name);
    }   
    
    public function deleteHotel()
    {
        $id = $this->input->post('hotelId');
        
        $this->load->model('hotels');
        
        if($this->hotels->deleteHotel($id)) {
            echo '1';
        } else {
            echo '0';
        }       
    }
    
    public function addNewElasticList($hotel_name)
    {       
        // Elastic URL parameters
        $apikey      = '3306af7f-2cf6-4e19-8fcd-d10eb9fd26f0';
        $elastic_listname    = $hotel_name;
        $allowunsubscribe    = 'true';
        
        // get URL for the Elastic Email Add Contact into the contact list
        $elastic_add_contact_url = "https://api.elasticemail.com/v2/list/add?".
                                    "apikey=".$apikey.
                                    "&listname=".$elastic_listname.
                                    "&addallcontacts=false".
                                    "&allowunsubscribe=".$allowunsubscribe;        

                
        $client = new GuzzleHttp\Client();
        
        // Send a request
        $response = $client->get($elastic_add_contact_url);

        // Get request body
        $result = (string) $response->getBody();
        $result = json_decode($result);

        return $result;
    }       
        
    public function logout()
    {
        unset($_SESSION['login']);
        header('Location: '.base_url());
    }
    
    public function sendEmail()
    {
        $config = Array(
            'protocol' => 'smtp',
            'smtp_host' => 'ssl://smtp.gmail.com',
            'smtp_port' => 465,
            'smtp_user' => 'neo.matrix.sba@gmail.com',
            'smtp_pass' => 'SbajgqbYLyc',
            'charset'   => 'iso-8859-1'
        );
        
        $this->load->library('email', $config);
        $this->email->set_newline("\r\n");

        $this->email->from('sakanyan.bagrat@gmail.com', 'My Name');
        $this->email->to('neo.matrix.sba@gmail.com');
        $this->email->subject('This is an email test');
        $this->email->message('Its working. Great!');

        if($this->email->send())
        {
            echo 'Your email was sent, dude.';
        }

        else
        {
            show_error($this->email->print_debugger());
        }
    }               
    
    public function getAllContacts()
    {
        // Set exectution time for long requests sendind
        ini_set('max_execution_time', 300);
        
        $this->load->model('users');
        
        // Get all contacts emails from db
        $result = $this->users->get_emails();
        
        // Array for all emails
        $emails = [];
        
        foreach ($result as $row) {            
            array_push($emails, $row->email);            
        }
        
        $this->addAllContactsToElastic($emails);
    }
    
    public function addAllContactsToElastic(Array $emails)
    {
        foreach ($emails as $email) {
            $this->addContactToElastic($email, 'first_list');
            sleep(1);
        }
        var_dump("DONE!");
    }

    public function login()
    {  
        if (isset($_SESSION['login'])) {
            
            $this->load->model('users');
            $data['hotspot_id'] = $this->users->get_hotspots();
           
            $this->load->view('user_page', $data);            
            
        } else {
            $this->load->helper(array('form', 'url'));
            $this->load->library('form_validation');

            $this->form_validation->set_rules('login', 'trim|required|min_length[5]|max_length[12]|xss_clean');
            $this->form_validation->set_rules('password', 'trim|required|md5');

            if ($this->form_validation->run() == false) {
                header('Location: '.base_url());
            } else {
                $login = $this->input->post('login');
                $password = $this->input->post('password');

                $this->load->model('users');

                $value = $this->users->login($login, $password);
                $data['login'] = $login;
                if ($value) {
                    $array = array('login' => $login);
                    $this->session->set_userdata($array);

                    header('Location: '.base_url());
                } else {
                    $message['message'] = 'Please try again';
                    $this->load->view('login_page', $message);
                    
                }
            }

        }


    }                

    public function get_hotspots()
    {
        if (isset($_SESSION['login'])) {
            $this->load->model('users');
            $hotspots['hotspots'] = $this->users->get_hotspots();
            $this->load->view('all_hotspots', $hotspots);
        } else {
            header('Location: '.base_url());
        }
    }

    public function get_hotspot($hotel_id = null)
    {
        if (isset($_SESSION['login'])) {
            if (isset($hotel_id)) {
                $this->load->model('users');
                $time = $this->users->get_hotspot($hotel_id);
                if (!empty($time)) {

                $days = 14 * 24 * 60 * 60;

                $from_time = strtotime($time[0]);
//                print_r($from_time);
                $to_last = strtotime(end($time));


                $to_time = $from_time + $days;
                $now = strtotime('now');
                $to = date('Y-m-d H:i:s', $to_time);
                $from = date('Y-m-d H:i:s', $from_time);
                if ($to_time > $to_last) {

                    $to_time = $to_last;
                }
                $excel_array = array();

                while ($to_time <= $to_last) {


                    $from = date('Y-m-d H:i:s', $from_time);
                    $to = date('Y-m-d H:i:s', $to_time);
                    $this->load->model('users');
                    $result = $this->users->get_excel($from, $to);

                    if (!empty($result)) {
                        $from_to = array();
                        $from_to['from'] = $from;
                        $from_to['to'] = $to;

                        array_push($excel_array, $from_to);

                    }

                    $to_time += $days;
                    $from_time += $days;
                    if ($to_time > $to_last) {

                        $to_time = $to_last;
                    }
                    if ($from_time > $to_last) {

                        break;
                    }

                }
                $data['hotspot_id'] = $hotel_id;
                $data['from_to_times'] = $excel_array;
                $this->load->view('all_hotspots', $data);
            }else {
                    header('Location: '.base_url());
                }

            } else {
                $this->load->model('users');
                $data['hotspot_id'] = $this->users->get_hotspots();

                $this->load->view('user_page', $data);
            }

        } else {
            header('Location: '.base_url());
        }

    }

    public function get_excel($from_time, $to_time)
    {
        if (isset($_SESSION['login'])) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/application/libraries/Classes/PHPExcel.php';
            $this->load->model('users');

            $data['excel'] = $this->users->get_excel($from_time, $to_time);

            $excel = $data['excel'];

            $data = array();
            foreach ($excel as $ex) {
                array_push($data, $ex);
            }

            $rows = array();
            $i = 0;
            foreach ($data as $item) {
                while ($i < count($item)) {
                    foreach ($item as $it => $key) {
                        array_push($rows, $it);
                        $i++;
                    }
                }
            }
            array_unshift($data, $rows);

            $objPHPExcel = new PHPExcel();

            $objPHPExcel->getActiveSheet()->fromArray($data, null, 'A1');
            $objPHPExcel->getActiveSheet()->setTitle('Members');

            foreach (range('A', $objPHPExcel->getActiveSheet()->getHighestDataColumn()) as $col) {
                $objPHPExcel->getActiveSheet()
                    ->getColumnDimension($col)
                    ->setAutoSize(true);
            }

            $c = $objPHPExcel->getActiveSheet()->getHighestDataColumn();

            $objPHPExcel->getActiveSheet()->getStyle('A1:' . $c . '1')->applyFromArray(
                array(
                    'font' => array(
                        'bold' => true
                    ),
                    'alignment' => array(
                        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_RIGHT,
                    ),
                    'borders' => array(
                        'top' => array(
                            'style' => PHPExcel_Style_Border::BORDER_THIN
                        )
                    ),
                    'fill' => array(
                        'type' => PHPExcel_Style_Fill::FILL_GRADIENT_LINEAR,
                        'rotation' => 90,
                        'startcolor' => array(
                            'argb' => 'FFA0A0A0'
                        ),
                        'endcolor' => array(
                            'argb' => 'FFFFFFFF'
                        )
                    )
                )
            );

            $time = date("d-m-Y_H_i");
            $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="Excel_' . $time . '.xls"');
            header('Cache-Control: max-age=0');
            $objWriter->save('php://output');

        } else {
            header('Location: '.base_url());
        }

    }

    public function get_csv($from_time, $to_time)
    {
        if (isset($_SESSION['login'])) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/application/libraries/Classes/PHPExcel.php';
            $this->load->model('users');

            $data['excel'] = $this->users->get_excel($from_time, $to_time);

            $excel = $data['excel'];

            $data = array();
            foreach ($excel as $ex) {
                array_push($data, $ex);
            }

            $rows = array();
            $i = 0;
            foreach ($data as $item) {
                while ($i < count($item)) {
                    foreach ($item as $it => $key) {
                        array_push($rows, $it);
                        $i++;
                    }
                }
            }
            array_unshift($data, $rows);

            $objPHPExcel = new PHPExcel();

            $objPHPExcel->getActiveSheet()->fromArray($data, null, 'A1');
            $objPHPExcel->getActiveSheet()->setTitle('Members');

            foreach (range('A', $objPHPExcel->getActiveSheet()->getHighestDataColumn()) as $col) {
                $objPHPExcel->getActiveSheet()
                    ->getColumnDimension($col)
                    ->setAutoSize(true);
            }

            $c = $objPHPExcel->getActiveSheet()->getHighestDataColumn();

            $objPHPExcel->getActiveSheet()->getStyle('A1:' . $c . '1')->applyFromArray(
                array(
                    'font' => array(
                        'bold' => true
                    ),
                    'alignment' => array(
                        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_RIGHT,
                    ),
                    'borders' => array(
                        'top' => array(
                            'style' => PHPExcel_Style_Border::BORDER_THIN
                        )
                    ),
                    'fill' => array(
                        'type' => PHPExcel_Style_Fill::FILL_GRADIENT_LINEAR,
                        'rotation' => 90,
                        'startcolor' => array(
                            'argb' => 'FFA0A0A0'
                        ),
                        'endcolor' => array(
                            'argb' => 'FFFFFFFF'
                        )
                    )
                )
            );

            $time = date("d-m-Y_H_i");
            $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'CSV');
            $objWriter->save('csv/Csv_' . $time . '.csv');
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="Csv_' . $time . '.csv"');
            header('Cache-Control: max-age=0');
            $objWriter->save('php://output');
        } else {
            header('Location: '.base_url());
        }
    }

    public function delete($from_time, $to_time)
    {
        if (isset($_SESSION['login'])) {
            $this->load->model('users');
            $this->users->delete($from_time, $to_time);
            $this->load->view('all_hotspots');
        } else {
            header('Location: '.base_url());
        }

    }

    public function hotspot_request($count, $hotspot_id = '4daa5936')
    {
        if (isset($_SESSION['login'])) {
            $this->load->model('users');
            $this->users->hotspot_request($count, $hotspot_id);
        } else {
            header('Location: '.base_url());
        }

    }

    public function createTemplateElasatic($hotel_name) {
        
                
        $client = new GuzzleHttp\Client();
        
        $response = $client->post('https://api.elasticemail.com/template/add?version=2', [
            'body' => [
                'apikey'       => '3306af7f-2cf6-4e19-8fcd-d10eb9fd26f0',
                'templatename' => $hotel_name.' Welcome',
                'templatetype' => 0,
                'subject'      => 'Welcome from the '.$hotel_name,
                'fromemail'    => 'admin@admin.com',
                'fromname'     => 'Admin',
                'bodyhtml'     => '<html><head></head><body><h1>'. $hotel_name .'</h1></body></html>',
                'bodytext'     => '',

            ]
        ]);

        // Get request body
        $result = (string) $response->getBody();                
        $result = json_decode($result);

        return $result;      
    }

}