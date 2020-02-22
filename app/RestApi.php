<?php

namespace App;

use Swift_Mailer;
use Swift_SmtpTransport;
use Swift_Message;
use Swift_TransportException;


class RestApi{
    
    public $config_smtp;
    public $config_telegram;

    public $rules_operator;
    public $rules_conditions;
    public $rules_type_effects = [];
        
    public $proj_on_sending;

    public $template_smtp;
    public $template_telegram;

    public $error_config_smtp = 0;
    public $error_config_telegram = 0;

    public $log_file_error = "error.log";
    public $log_file_sending = "sending.log";

    public function __construct($projects){ 
        $tmp = get_config();
        $this->config_smtp = $tmp["smtp"];
        $this->config_telegram = $tmp["telegram"];
        
        $tmp = json_decode(file_get_contents('app/rules.json'),true)['rules'][0];
        $this->rules_operator = $tmp["operator"];
        $this->rules_conditions = $tmp["conditions"];
        $effects = $tmp["effects"];
        foreach($effects as $effect){
            $this->rules_type_effects[$effect['type']] = $effect;
        }
        
        $this->proj_on_sending = $this->get_proj_on_send($this->rules_operator, $this->rules_conditions, $projects);
        $this->template_smtp = $this->config_smtp["templates"][$this->rules_type_effects["smtp"]["template_id"]];
        $this->template_telegram = $this->config_telegram["templates"][$this->rules_type_effects["telegram"]["template_id"]];
    }


    public function log_write($file_name, $msg){
        $file_path = "tmp/" . $file_name;
        $date = date("d.m.Y H:i:s");
                
        $fd = fopen($file_path, 'a') or die('not open file: ' . $file_name);
        $msg = $date."\n\t".$msg."\n\n";
                
        fwrite($fd, $msg);
        fclose($fd);
    }

    public function send_all(){
        
        foreach($this->proj_on_sending as $project){
            $template_smtp = $this->get_template($project, "smtp", $this->template_smtp);
            $template_telegram = $this->get_template($project, "telegram", $this->template_telegram);

            $this->smtp_sender($template_smtp);
            $this->telegram_sender($template_telegram);
        }
    }

    function get_template($project, $type, $template){
        
        $temps = $template;
        $i = 0;
        foreach($this->rules_type_effects[$type]["placeholders"] as $pl_key => $pl_val){
            foreach($temps as $k_temp => $v_temp){
                $temps[$k_temp] = str_replace("%{$pl_key}%", $project[$pl_val], $temps[$k_temp]);
            }
        }
        return $temps;
    }


    public function smtp_sender($template){
        if($this->error_config_smtp){
            return;
        }
        $transport = (new Swift_SmtpTransport($this->config_smtp['host'],
        $this->config_smtp['port'], $this->config_smtp['protocol']))
        ->setUsername($this->config_smtp['login'])
        ->setPassword($this->config_smtp['password']);
                
        $mailer = new Swift_Mailer($transport);
        
        $message = (new Swift_Message($template['subject']))
                ->setFrom([$this->config_smtp['fromEmail'] => $this->config_smtp['name']])
                ->setTo([$this->config_smtp['toEmail']])
                ->setBody($template['message'], 'text/html');

        try{
            $mailer->send($message);
            $msg = "\tSMTP Ok:"
                ."\n\t"."\"subject\":".$template['subject']
                ."\n\t"."\"message\":".$template['message'];
            $this->log_write($this->log_file_sending, $msg);
            echo "send on smtp\n";
        }catch(Swift_TransportException $e){
            $msg = "SMTP connection ERROR";
            $this->log_write($this->log_file_error, $msg);
            echo "sorry! some problem with SMTP\n";
            $this->error_config_smtp = 1;
        }
        
    }

    function telegram_sender($template){
        if($this->error_config_telegram){
            return;
        }
        $botApiToken = $this->config_telegram['token'];
        $data = [
            'chat_id' => $this->config_telegram['chat_id'],
            'text' => $template["message"]
        ];
                
        $query = "https://api.telegram.org/bot{$botApiToken}/sendMessage?".http_build_query($data);
        
        if(file_get_contents($query)){
            $msg = "\ttelegram send is Ok"."\n\t".$template["message"];
            $this->log_write($this->log_file_sending, $msg);
            echo "send on telegram\n";
        }else{
            $msg = "Telegram connection ERROR";
            $this->log_write($this->log_file_error, $msg);
            echo "sorry! some problem with telegram\n";
            $this->error_config_telegram = 1;
        }
    }


    public function is_condition($project, $condition){
        switch ($condition["condition"]){
            case "equal":
                return (int)($project[$condition["key"]] == $condition["val"]);
            case "inArray":
                return (int)in_array($condition["val"], $project[$condition["key"]]);
            case "moreThan":
                return (int)($project[$condition["key"]] > $condition["val"]);
            case "lessThan":
                return (int)($project[$condition["key"]] < $condition["val"]);
        }
    }
    

    public function get_proj_on_send($operator, $conditions, $projects){
        $proj_on_sending = [];
        switch ($operator){
            case "and":
                foreach($projects as $project){
                    $is_condition = [];
                    foreach($conditions as $condition){
                        array_push($is_condition ,$this->is_condition($project, $condition));
                    }
                    if(!in_array(0, $is_condition)){
                        array_push($proj_on_sending, $project);
                    }
                }
            break;
    
            case "or":
                foreach($projects as $project){
                    $is_condition = [];
                    foreach($conditions as $condition){
                        array_push($is_condition ,$this->is_condition($project, $condition));
                    }
                    if(in_array(1, $is_condition)){
                        array_push($proj_on_sending, $project);
                    }
                }
            break;
    
            default:
                include "app/page_404.php";
        }
        return $proj_on_sending;
    }
    

}

