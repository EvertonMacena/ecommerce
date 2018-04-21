<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;
use \Hcode\Mailer;

class User extends Model{

    const SESSION = "User";
    const SECRET = "LojavirtualPhp79";
    const ERROR = "UserErro";
    const ERROR_REGISTER = "UserErrorRegister";
    const SUCESS = "Sucesso";

    public static function getFromSession(){

         $user = new User();

        if (isset($_SESSION[User::SESSION])&& (int)$_SESSION[User::SESSION]['iduser'] >0 ){

            $user->setData($_SESSION[User::SESSION]);

        }
        return $user;
    }

    public static function checkLogin($inadmin  = true){
        if (
            !isset($_SESSION[User::SESSION]) ||
            !$_SESSION[User::SESSION] ||
            !(int)$_SESSION[User::SESSION]["iduser"] >0
        ){
            //Ususario nao estar logado
            return false;

        } else {
            // Usuario estar logado
            if($inadmin === true && (bool)$_SESSION[User::SESSION]["inadmin"] === true){
                //Usuario é admin
                return true;

            } else if ($inadmin === false) {
                return true;
            } else {
                return false;
            }
        }
    }

    public static function login($login, $password){

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b ON a.idperson = b.idperson WHERE a.deslogin = :LOGIN", array(":LOGIN"=>$login));

        if (count($results) === 0){
            throw new \Exception ("Usuário inexistente ou senha iinvalida");
        }

        $data = $results[0];

        if (password_verify($password, $data["despassword"]) === true){

            $user = new User();

            $data['desperson'] = utf8_encode($data['desperson']);

            $user->setData($data);

            $_SESSION[User::SESSION] = $user->getValues();

            return $user;

        } else {
            throw new \Exception("Usuário ou senha invalida");
        }



    }

    public static function verify_login($inadmin  = true){
        if (!User::checkLogin($inadmin)){

            if($inadmin){
                header("Location: /admin/login");
                exit;
            } else {
                header("Location: /login");
                exit;
            }
        }
    }

    public static function logout(){

        $_SESSION[User::SESSION] = NULL;
    }

    public static function listAll(){
        $sql = new Sql();

        return $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) ORDER BY b.desperson");
    }

    public function save(){
        $sql = new Sql();
        $results = $sql->select("CALL sp_users_save(:desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array(
            ":desperson"=>utf8_decode($this->getdesperson()),
            ":deslogin"=>$this->getdeslogin(),
            ":despassword"=>User::getPasswordHash($this->getdespassword()),
            ":desemail"=>$this->getdesemail(),
            ":nrphone"=>$this->getnrphone(),
            ":inadmin"=>$this->getinadmin()

        ));

        $this->setData($results[0]);
    }

    public function get($iduser){
        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) WHERE a.iduser = :iduser", array(":iduser"=>$iduser));
        $data = $results[0];

        $data['desperson'] = utf8_encode($data['desperson']);

        $this->setData($data);

    }

    public function update(){

        $sql = new Sql();

         $results = $sql->select("CALL sp_usersupdate_save(:iduser,:desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array(
            ":iduser"=>$this->getiduser(),
            ":desperson"=>utf8_decode($this->getdesperson()),
            ":deslogin"=>$this->getdeslogin(),
            ":despassword"=>User::getPasswordHash($this->getdespassword()),
            ":desemail"=>$this->getdesemail(),
            ":nrphone"=>$this->getnrphone(),
            ":inadmin"=>$this->getinadmin()

        ));
         $this->setData($results[0]);
    }

    public function delete(){

        $sql = new Sql();

        $sql->query("CALL sp_users_delete(:iduser)", array(
            ":iduser"=>$this->getiduser()));
    }

    public static function getForgot($email, $inadmin = true){
        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_persons a INNER JOIN tb_users b USING(idperson) WHERE a.desemail = :email", array(":email"=>$email));

        if (count($results) === 0 ){
            throw new \Exception("Não foi possivel recuperar a senha");

        }else {

            $data = $results[0];
            $results2 = $sql->select("CALL sp_userspasswordsrecoveries_create(:iduser, :desip)", array(":iduser"=>$data["iduser"],
                        ":desip"=>$_SERVER["REMOTE_ADDR"]));

            if(count($results2)===0){
                throw new \Exception("Não foi possivel recuperar a senha");
            } else {
                $dataRecovery = $results2[0];

                $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));

                $code = openssl_encrypt($dataRecovery['idrecovery'], 'aes-256-cbc', User::SECRET, 0, $iv);

                if ($inadmin){

                     $link = "http://www.lojavirtual.com.br/admin/forgot/reset?code=$code";

                } else {

                     $link = "http://www.lojavirtual.com.br/forgot/reset?code=$code";
                }

                $mailer = new Mailer($data["desemail"], $data["desperson"], "Redefinir senha loja virtual", "mail-forgot", array("name" => $data["desperson"], "link" => $link));

                $mailer->send();

                return $data;
            }

        }
    }
   public static function validForgotDecrypt($result)
 {
     $result = base64_decode($result);
     $code = mb_substr($result, openssl_cipher_iv_length('aes-256-cbc'), null, '8bit');
     $iv = mb_substr($result, 0, openssl_cipher_iv_length('aes-256-cbc'), '8bit');;
     $idrecovery = openssl_decrypt($code, 'aes-256-cbc', User::SECRET, 0, $iv);
     $sql = new Sql();
     $results = $sql->select("
         SELECT *
         FROM tb_userspasswordsrecoveries a
         INNER JOIN tb_users b USING(iduser)
         INNER JOIN tb_persons c USING(idperson)
         WHERE
         a.idrecovery = :idrecovery
         AND
         a.dtrecovery IS NULL
         AND
         DATE_ADD(a.dtregister, INTERVAL 1 HOUR) >= NOW();
     ", array(
         ":idrecovery"=>$idrecovery
     ));
     if (count($results) === 0)
     {
         throw new \Exception("Não foi possível recuperar a senha.");
     }
     else
     {
         return $results[0];
     }
    }

    public static function setForgotUsed($idrecovery){

        $sql = new Sql();

        $sql->query("UPDATE tb_userspasswordsrecoveries SET dtrecovery = NOW() WHERE idrecovery = :idrecovery", array(":idrecovery"=>$idrecovery));
    }

    public function setPassword($password){
        $sql = new Sql();

        $sql->query("UPDATE tb_users SET despassword = :password WHERE iduser = :iduser", array(":password"=>$password, ":iduser"=>$this->getiduser()));
    }

    public static function setMsgErro ($msg){

        $_SESSION[User::ERROR] = $msg;

    }

    public static function getMsgErro (){

        $msg =  (isset($_SESSION[User::ERROR]) && $_SESSION[User::ERROR]) ? $_SESSION[User::ERROR] : "";

        User::clearMsgErro();

        return $msg;
    }

    public static function clearMsgErro (){

        $_SESSION[User::ERROR] = NULL;

    }


    public static function setSucess ($msg){

        $_SESSION[User::SUCESS] = $msg;

    }

    public static function getSucess (){

        $msg =  (isset($_SESSION[User::SUCESS]) && $_SESSION[User::SUCESS]) ? $_SESSION[User::SUCESS] : "";

        User::clearSucess();

        return $msg;
    }

    public static function clearSucess (){

        $_SESSION[User::SUCESS] = NULL;

    }

    public static function setErroRegister($msg){
        $_SESSION[User::ERROR_REGISTER] = $msg;
    }

    public static function getErroRegister (){
        $msg = (isset($_SESSION[User::ERROR_REGISTER]) && $_SESSION[User::ERROR_REGISTER]) ? $_SESSION[User::ERROR_REGISTER] : '';

        User::clearErroRegister();

        return $msg;
    }

    public static function clearErroRegister(){
        $_SESSION[User::ERROR_REGISTER] = NULL;
    }

    public static function getPasswordHash($password){
        return password_hash($password, PASSWORD_DEFAULT, [
            'cost'=>12]);
    }

    public static function checkLoginExist($login){
       $sql = new Sql();

       $results = $sql->select("SELECT * FROM tb_users WHERE deslogin = :deslogin", [
        ':deslogin'=>$login]);

       return (count($results)>0);
    }

    public function getOrders(){
        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_orders a INNER JOIN tb_ordersstatus b USING(idstatus)
            INNER JOIN tb_carts c USING(idcart)
            INNER JOIN tb_users d ON d.iduser = a.iduser
            INNER JOIN tb_addresses e USING(idaddress)
            INNER JOIN tb_persons f ON f.idperson = d.idperson
            WHERE a.iduser = :iduser", [
                ':iduser'=>$this->getiduser()]);

        return $results[0];
    }



}
?>