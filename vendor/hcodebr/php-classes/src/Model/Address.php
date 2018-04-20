<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;
use \Hcode\Mailer;
use \Hcode\Model\User;

class Address extends Model{

    const SESSION_ERROR = "AddressError";

    public static function getCEP($nrcep){

        $nrcep = str_replace("-", "", $nrcep);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://viacep.com.br/ws/$nrcep/json/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $data = json_decode(curl_exec($ch), true);

        curl_close($ch);

        return $data;
    }

    public function loadFromCep($nrcep){

        $data = Address::getCEP($nrcep);

        //if(isset($data['cidade']) && $data['cidade']){
            $this->setdesaddress($data['logradouro']);
            $this->setdescomplement($data['complemento']);
            $this->setdesdistrict($data['bairro']);
            $this->setdescity($data['localidade']);
            $this->setdesstate($data['uf']);
            $this->setdescountry('Brasil');
            $this->setdeszipcode($nrcep);

        //}
    }

    public function save(){

        $sql = new Sql();

        $results = $sql->select("CALL sp_addresses_save(:idaddress, :idperson, :desaddress, :descomplement, :descity, :desstate, :descountry, :deszipcode, :desdistrict)", [
            ':idaddress'=>$this->getidaddress(),
            ':idperson'=>$this->getidperson(),
            ':desaddress'=>utf8_decode($this->getdesaddress()),
            ':descomplement'=>utf8_decode($this->getdescomplement()),
            ':descity'=>utf8_decode($this->getdescity()),
            ':desstate'=>$this->getdesstate(),
            ':descountry'=>utf8_decode($this->getdescountry()),
            ':deszipcode'=>$this->getdeszipcode(),
            ':desdistrict'=>utf8_decode($this->getdesdistrict())]);

        if (count($results)> 0){
            $this->setData($results[0]);
        }
    }


        public static function setMsgErro ($msg){

            $_SESSION[Address::SESSION_ERROR] = $msg;

        }

        public static function getMsgErro (){

            $msg =  (isset($_SESSION[Address::SESSION_ERROR])) ? $_SESSION[Address::SESSION_ERROR] : "";

            Address::clearMsgErro();

            return $msg;
        }

        public static function clearMsgErro (){

            $_SESSION[Address::SESSION_ERROR] = NULL;
        }
}

?>