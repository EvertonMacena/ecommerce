<?php

session_start();

require_once("vendor/autoload.php");
require("functions.php");

use \Slim\Slim;
use \Hcode\Page;
use \Hcode\PageAdmin;
use \Hcode\Model\User;
use \Hcode\Model\Category;
use \Hcode\Model\Product;
use \Hcode\Model\Cart;
use \Hcode\Model\Address;
use \Hcode\Model\Order;
use \Hcode\Model\OrderStatus;


$app = new Slim();

$app->config('debug', true);

/**
require_once("admin.php");
require_once("site.php");
require_once("admin-user.php");
require_once("admin-categories.php");
require_once("admin-products.php");
*/
$app->get('/', function() {

    $products = Product::listAll();
    $page = new Page();
    $page->setTpl("index", array("produtos" => Product::checkList($products)));
});

$app->get('/admin', function() {

    User::verify_login();

    $page = new PageAdmin();
    $page->setTpl("index");
});

$app->get("/categories/:idcategory", function($idcategory){
    $pages = (isset($_GET['page'])) ? (int)$_GET['page'] : 1;

    $categoria = new Category();

    $categoria->get((int)$idcategory);

    $page = new Page();

    $pagination = $categoria->getProductsPage($pages);

    $pages = [];

    for ($i=1; $i <=$pagination['pages'] ; $i++) {
        array_push($pages, [
            'link'=> "/categories/".$categoria->getidcategory()."?page=".$i,
            'page'=> $i]);
    }

    $page->setTpl("category", ['categoria'=>$categoria->getValues(), 'products'=>$pagination["data"], 'pages'=>$pages]);
});

$app->get("/products/:desurl", function($desurl){
    $product = new Product();

    $product->getFromURL($desurl);

    $page = new Page();

    $page->setTpl("product-detail", [
        'product'=>$product->getValues(),
        'categories'=>$product->getCategories()
    ]);
});

$app->get("/cart", function(){

    $cart = Cart::getFromSession();

    $page = new Page();

    $page->setTpl("cart", [
        'carrinho'=>$cart->getValues(),
        'produtos'=>$cart->getProducts(),
        'error'=>Cart::getMsgErro()]);
});

$app->get("/checkout", function(){

    User::verify_login(false);

    $address = new Address();

    $cart = Cart::getFromSession();

    if (!isset($_GET['zipcode'])){

        $_GET['zipcode'] = $cart->getdeszipcode();

    }

    if (isset($_GET['zipcode'])){
        $address->loadFromCep($_GET['zipcode']);

        $cart->setdeszipcode($_GET['zipcode']);

        $cart->save();

        $cart->getCalculateTotal();
    }

    if (!$address->getdesaddress()) $address->setdesaddress('');
    if (!$address->getdescomplement()) $address->setdescomplement('');
    if (!$address->getdesdistrict()) $address->setdesdistrict('');
    if (!$address->getdescity()) $address->setdescity('');
    if (!$address->getdesstate()) $address->setdesstate('');
    if (!$address->getdescountry()) $address->setdescountry('');
    if (!$address->getdeszipcode()) $address->setdeszipcode('');

    $page = new Page();

    $page->setTpl("checkout", [
        'cart'=>$cart->getValues(),
        'address'=>$address->getValues(),
        'error'=>Address::getMsgErro(),
        'products'=>$cart->getProducts()]);
});

$app->post("/checkout", function(){

    User::verify_login(false);

     if (!isset($_POST['zipcode']) || $_POST['zipcode'] === "" ){
        Address::setMsgErro("Informe o CEP");
        header("Location: /checkout");
        exit;
    }

    if (!isset($_POST['desaddress']) || $_POST['desaddress'] === "" ){
        Address::setMsgErro("Digite o endereco");
        header("Location: /checkout");
        exit;
    }
    if (!isset($_POST['desdistrict']) || $_POST['desdistrict'] === "" ){
        Address::setMsgErro("Digite o bairro");
        header("Location: /checkout");
        exit;
    }
    if (!isset($_POST['descity']) || $_POST['descity'] === "" ){
        Address::setMsgErro("Digite a cidade");
        header("Location: /checkout");
        exit;
    }
    if (!isset($_POST['desstate']) || $_POST['desstate'] === "" ){
        Address::setMsgErro("Digite o estado");
        header("Location: /checkout");
        exit;
    }

    $user = User::getFromSession();

    $address = new Address();

    $_POST['deszipcode'] = $_POST['zipcode'];
    $_POST['idperson'] = $user->getidperson();

    $address->setData($_POST);

    $address->save();

    $cart = Cart::getFromSession();

    $totals = $cart->getCalculateTotal();

    $order = new Order();

    $order->setData([
        'idcart'=>$cart->getidcart(),
        'idaddress'=>$address->getidaddress(),
        'iduser'=>$user->getiduser(),
        'idstatus'=>OrderStatus::EM_ABERTO,
        'vltotal'=>$totals['vlprice']+$cart->getvlfreight()]);

    $order->save();

    header("Location: /order/".$order->getidorder());
    exit;
});

$app->get("/order/:idorder", function($idorder){

    User::verify_login(false);

    $order = new Order();

    $order->get((int)$idorder);

    $page = new Page();

    $page->setTpl("payment", [
        'order'=> $order->getValues()]);
});

$app->get("/boleto/:idorder", function($idorder){

    User::verify_login(false);

    $order = new Order();

    $order->get((int)$idorder);

    // DADOS DO BOLETO PARA O SEU CLIENTE
    $dias_de_prazo_para_pagamento = 10;
    $taxa_boleto = 5.00;
    $data_venc = date("d/m/Y", time() + ($dias_de_prazo_para_pagamento * 86400));  // Prazo de X dias OU informe data: "13/04/2006";
    $valor_cobrado = formatPrice($order->getvltotal()); // Valor - REGRA: Sem pontos na milhar e tanto faz com "." ou "," ou com 1 ou 2 ou sem casa decimal
    $valor_cobrado = str_replace(",", ".",$valor_cobrado);
    $valor_boleto=number_format($valor_cobrado+$taxa_boleto, 2, ',', '');

    $dadosboleto["nosso_numero"] = $order->getidorder();  // Nosso numero - REGRA: Máximo de 8 caracteres!
    $dadosboleto["numero_documento"] = $order->getidorder();  // Num do pedido ou nosso numero
    $dadosboleto["data_vencimento"] = $data_venc; // Data de Vencimento do Boleto - REGRA: Formato DD/MM/AAAA
    $dadosboleto["data_documento"] = date("d/m/Y"); // Data de emissão do Boleto
    $dadosboleto["data_processamento"] = date("d/m/Y"); // Data de processamento do boleto (opcional)
    $dadosboleto["valor_boleto"] = $valor_boleto;   // Valor do Boleto - REGRA: Com vírgula e sempre com duas casas depois da virgula

    // DADOS DO SEU CLIENTE
    $dadosboleto["sacado"] = $order->getdesperson();
    $dadosboleto["endereco1"] = $order->getdesaddress()." ".$order->getdesdistrict();
    $dadosboleto["endereco2"] = $order->getdescity()." - ". $order->getdesstate(). " - CEP: ". $order->getdeszipcode();;

    // INFORMACOES PARA O CLIENTE
    $dadosboleto["demonstrativo1"] = "Pagamento de Compra na Loja Hcode E-commerce";
    $dadosboleto["demonstrativo2"] = "Taxa bancária - R$ 0,00";
    $dadosboleto["demonstrativo3"] = "";
    $dadosboleto["instrucoes1"] = "- Sr. Caixa, cobrar multa de 2% após o vencimento";
    $dadosboleto["instrucoes2"] = "- Receber até 10 dias após o vencimento";
    $dadosboleto["instrucoes3"] = "- Em caso de dúvidas entre em contato conosco: suporte@hcode.com.br";
    $dadosboleto["instrucoes4"] = "&nbsp; Emitido pelo sistema Projeto Loja Hcode E-commerce - www.hcode.com.br";

    // DADOS OPCIONAIS DE ACORDO COM O BANCO OU CLIENTE
    $dadosboleto["quantidade"] = "";
    $dadosboleto["valor_unitario"] = "";
    $dadosboleto["aceite"] = "";
    $dadosboleto["especie"] = "R$";
    $dadosboleto["especie_doc"] = "";


    // ---------------------- DADOS FIXOS DE CONFIGURAÇÃO DO SEU BOLETO --------------- //


    // DADOS DA SUA CONTA - ITAÚ
    $dadosboleto["agencia"] = "1690"; // Num da agencia, sem digito
    $dadosboleto["conta"] = "48781";    // Num da conta, sem digito
    $dadosboleto["conta_dv"] = "2";     // Digito do Num da conta

    // DADOS PERSONALIZADOS - ITAÚ
    $dadosboleto["carteira"] = "175";  // Código da Carteira: pode ser 175, 174, 104, 109, 178, ou 157

    // SEUS DADOS
    $dadosboleto["identificacao"] = "Hcode Treinamentos";
    $dadosboleto["cpf_cnpj"] = "24.700.731/0001-08";
    $dadosboleto["endereco"] = "Rua Ademar Saraiva Leão, 234 - Alvarenga, 09853-120";
    $dadosboleto["cidade_uf"] = "São Bernardo do Campo - SP";
    $dadosboleto["cedente"] = "HCODE TREINAMENTOS LTDA - ME";

    // NÃO ALTERAR!
    $path = $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR."res".DIRECTORY_SEPARATOR."boletophp".DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR;

    require_once($path."funcoes_itau.php");
    require_once($path."layout_itau.php");
});

$app->get("/login", function(){

    $page = new Page();

    $page->setTpl("login", [
        'error'=>User::getMsgErro(),
        'errorRegister'=>User::getErroRegister(),
        'registerValues'=>(isset($_SESSION['registerValues'])) ? $_SESSION['registerValues'] : ['name'=>'', 'email'=>'','phone'=>'']]);
});

$app->get("/logout", function(){

    User::logout();
    header("Location: /login");
    exit;

});

$app->post("/login", function(){

    try{

        User::login($_POST['login'], $_POST['password']);

    } catch (Exception $e){

        User::setMsgErro($e->getMessage());

    }

    header("Location: /checkout");
    exit;
});

$app->get("/profile", function(){

    User::verify_login(false);

    $user = User::getFromSession();

    $page = new Page();

    $page->setTpl("profile", [
        'user'=>$user->getValues(),
        'profileMsg'=>User::getSucess(),
        'profileError'=>User::getMsgErro()]);
});

$app->post("/profile", function (){

    User::verify_login(false);

    if (!isset($_POST['desperson']) || $_POST['desperson'] === "" ){
        User::setMsgErro("Digite o nome");
        header("Location: /profile");
        exit;
    }

    if (!isset($_POST['desemail']) || $_POST['desemail'] === "" ){
        User::setMsgErro("Digite o email");
        header("Location: /profile");
        exit;
    }

    $user = User::getFromSession();

    if ($_POST['desemail'] !== $user->getdesemail()){

        if (User::checkLoginExist($_POST['deslogin'])){
            User::setMsgErro("Este usuario já existe");
            header("Location: /profile");
            exit;
        }
    }

    $_POST['iduser'] = $user->getiduser();
    $_POST['inadmin'] = $user->getinadmin();
    $_POST['despassword'] = $user->getdespassword();
    $_POST['deslogin'] = $_POST['desemail'];

    $user->setData($_POST);

    $user->update();

    $_SESSION[User::SESSION] = $user->getValues();

    User::setSucess("Dados alterado com sucesso");
    header("Location: /profile");
    exit;
});

$app->post("/register", function(){

    $_SESSION['registerValues'] = $_POST;

    if(!isset($_POST['name']) || $_POST['name'] == ''){
        User::setErroRegister("Preencha o nome corretamente !");
        header("Location: /login");
        exit;
    }

    if(!isset($_POST['email']) || $_POST['email'] == ''){
        User::setErroRegister("Preencha o email corretamente !");
        header("Location: /login");
        exit;
    }

    if(!isset($_POST['password']) || $_POST['password'] == ''){
        User::setErroRegister("Preencha a senha corretamente !");
        header("Location: /login");
        exit;
    }

    if(User::checkLoginExist($_POST['email']) === true){
        User::setErroRegister("Email já é usado por um outro usuario");
        header("Location: /login");
        exit;
    }

    $user = new User();

    $user->setData([
        'inadmin'=>0,
        'deslogin'=>$_POST['email'],
        'desperson'=>$_POST['name'],
        'desemail'=>$_POST['email'],
        'despassword'=>$_POST['password'],
        'nrphone'=>$_POST['phone']]);

    $user->save();

    User::login($_POST['email'], $_POST['password']);

    header("Location: /checkout");
    exit;

});


$app->get("/forgot", function(){

    $page = new Page();
    $page->setTpl("forgot");
});

$app->post("/forgot", function(){

    $user = User::getForgot($_POST["email"], false);

    header("Location: /forgot/sent");

    exit;

});

$app->get("/forgot/sent", function (){

    $page = new Page();
    $page->setTpl("forgot-sent");
});

$app->get("/forgot/reset", function(){

    $user = User::validForgotDecrypt($_GET["code"]);

    $page = new Page();
    $page->setTpl("forgot-reset", array("name"=>$user["desperson"], "code"=>$_GET["code"]));
});

$app->post("/forgot/reset", function(){
    $forgot = User::validForgotDecrypt($_GET["code"]);

    User::setForgotUsed($user["idrecovery"]);

    $user = new User();

    $user->get((int)$forgot["iduser"]);

    $password = password_hash($_POST["password"], PASSWORD_DEFAULT, ["cost"=>12]);

    $user->setPassword($password);

    $page = new Page();
    $page->setTpl("forgot-reset-success");

});

$app->get("/cart/:idproduct/add", function($idproduct){

    $product = new Product();

    $product->get((int)$idproduct);

    $cart = Cart::getFromSession();

    $qtd = (isset($_GET['qtd'])) ? (int)$_GET['qtd'] : 1;

    for ($i = 0; $i < $qtd; $i++){
        $cart->addProduct($product);
    }

    header("Location: /cart");
    exit;
});

$app->get("/cart/:idproduct/minus", function($idproduct){

    $product = new Product();

    $product->get((int)$idproduct);

    $cart = Cart::getFromSession();

    $cart->removeProduct($product);

    header("Location: /cart");
    exit;
});

$app->get("/cart/:idproduct/remove", function($idproduct){

    $product = new Product();

    $product->get((int)$idproduct);

    $cart = Cart::getFromSession();

    $cart->removeProduct($product, true);

    header("Location: /cart");
    exit;
});

$app->post("/cart/freight", function(){

    $cart = Cart::getFromSession();

    $cart->setFreight($_POST['zipcode']);

    header("Location: /cart");
    exit;
});

$app->get('/admin/login', function(){

    $page = new PageAdmin(["header"=>false, "footer"=>false]);
    $page->setTpl("login");
});

$app->post('/admin/login', function(){
    User::login($_POST["login"], $_POST["password"]);

    header ("Location: /admin");
    exit;
});

$app-> get('/admin/logout', function(){
    User::logout();

    header("Location: /admin/login");
    exit;
});


$app->get("/admin/forgot", function(){

    $page = new PageAdmin(["header"=>false, "footer"=>false]);
    $page->setTpl("forgot");
});

$app->post("/admin/forgot", function(){

    $user = User::getForgot($_POST["email"]);

    header("Location: /admin/forgot/sent");

    exit;

});

$app->get("/admin/forgot/sent", function (){

    $page = new PageAdmin(["header"=>false, "footer"=>false]);
    $page->setTpl("forgot-sent");
});

$app->get("/admin/forgot/reset", function(){

    $user = User::validForgotDecrypt($_GET["code"]);

    $page = new PageAdmin(["header"=>false, "footer"=>false]);
    $page->setTpl("forgot-reset", array("name"=>$user["desperson"], "code"=>$_GET["code"]));
});

$app->post("/admin/forgot/reset", function(){
    $forgot = User::validForgotDecrypt($_GET["code"]);

    User::setForgotUsed($user["idrecovery"]);

    $user = new User();

    $user->get((int)$forgot["iduser"]);

    $password = password_hash($_POST["password"], PASSWORD_DEFAULT, ["cost"=>12]);

    $user->setPassword($password);

    $page = new PageAdmin(["header"=>false, "footer"=>false]);
    $page->setTpl("forgot-reset-success");

});

$app->get("/admin/users", function(){

    User::verify_login();

    $users = User::listAll();

    $page = new PageAdmin();

    $page->setTpl("users", array(
        "users" => $users ));
});

$app->get("/admin/users/create", function(){

    User::verify_login();

    $page = new PageAdmin();

    $page->setTpl("users-create");
});
$app->get("/admin/users/:iduser/delete", function($iduser){

    User::verify_login();

    $user = new User();

    $user->get((int)$iduser);

    $user->delete();

    header("Location: /admin/users");

    exit;
});

$app->get("/admin/users/:iduser", function($iduser){

    User::verify_login();

    $page = new PageAdmin();

    $user = new User();

    $user->get((int)$iduser);

    $page->setTpl("users-update", array("user"=>$user->getValues()));
});

$app->post("/admin/users/create", function(){

    User::verify_login();

    $user = new User();

    $_POST["inadmin"] = (isset($_POST["inadmin"])) ?1:0;

    $user->setData($_POST);

    $user->save();

    header("Location: /admin/users");

    exit;
});

$app->post("/admin/users/:iduser", function($iduser){

    User::verify_login();

    $user = new User();

    $_POST["inadmin"] = (isset($_POST["inadmin"])) ?1:0;

    $user->get((int)$iduser);

    $user->setData($_POST);

    $user->update();

    header("Location: /admin/users");

    exit;
});


$app->get("/admin/categories", function(){

    User::verify_login();

    $categories = Category::listAll();

    $page = new PageAdmin();

    $page->setTpl("categories", ["categories"=>$categories]);
});

$app->get("/admin/categories/create",function(){

    User::verify_login();

    $page = new PageAdmin();

    $page->setTpl("categories-create");
});

$app->post("/admin/categories/create",function(){

    User::verify_login();

    $category = new Category();

    $category->setData($_POST);

    $category->save();

    header('Location: /admin/categories');

    exit;
});

$app->get("/admin/categories/:idcategory/delete", function($idcategory){

    User::verify_login();

    $category = new Category();

    $category->get((int)$idcategory);

    $category->delete();

    header('Location: /admin/categories');

    exit;
});

$app->get("/admin/categories/:idcategory", function($idcategory){
    User::verify_login();

    $category = new Category();

    $category->get((int)$idcategory);

    $page = new PageAdmin();

    $page->setTpl("categories-update", ['category'=>$category->getValues()]);
});

$app->post("/admin/categories/:idcategory", function($idcategory){
    User::verify_login();

    $category = new Category();

    $category->get((int)$idcategory);

    $category->setData($_POST);

    $category->save();

    header('Location: /admin/categories');

    exit;
});

$app->get("/admin/categories/:idcategory/products", function($idcategory){
    User::verify_login();

    $category = new Category();

    $category->get((int)$idcategory);

    $page = new PageAdmin();

    $page->setTpl("categories-products", ['category'=>$category->getValues(),
        'productsRelated'=>$category->getProducts(),
        'productsNotRelated'=>$category->getProducts(false)]);
});

$app->get("/admin/categories/:idcategory/products/:idproduct/add", function($idcategory, $idproduct){
    User::verify_login();

    $category = new Category();

    $category->get((int)$idcategory);

    $product = new Product();

    $product->get((int)$idproduct);

    $category->addProduct($product);

    header("Location: /admin/categories/".$idcategory."/products");
    exit;
});

$app->get("/admin/categories/:idcategory/products/:idproduct/remove", function($idcategory, $idproduct){
    User::verify_login();

    $category = new Category();

    $category->get((int)$idcategory);

    $product = new Product();

    $product->get((int)$idproduct);

    $category->removeProduct($product);

    header("Location: /admin/categories/".$idcategory."/products");
    exit;
});


$app->get("/admin/products", function(){

    User::verify_Login();

    $products = Product::listAll();

    $page = new PageAdmin();

    $page->setTpl("products", ["products"=>$products]);
});

$app->get("/admin/products/create", function(){

    User::verify_Login();

    $page = new PageAdmin();

    $page->setTpl("products-create");
});

$app->post("/admin/products/create", function(){

    User::verify_Login();

    $product = new Product();

    $product->setData($_POST);

    $product->save();

    header("Location: /admin/products");
    exit;
});

$app->get("/admin/products/:idproduct", function($idproduct){

    User::verify_Login();

    $product = new Product();

    $product->get((int)$idproduct);

    $page = new PageAdmin();

    $page->setTpl("products-update",['product'=>$product->getValues()]);
});

$app->post("/admin/products/:idproduct", function($idproduct){

    User::verify_Login();

    $product = new Product();

    $product->get((int)$idproduct);

    $product->setData($_POST);

    $product->save();

    $product->setPhoto($_FILES["file"]);

    header("Location: /admin/products");

    exit;
});

$app->get("/admin/products/:idproduct/delete", function($idproduct){

    User::verify_Login();

    $product = new Product();

    $product->get((int)$idproduct);

    $product->delete();

     header("Location: /admin/products");

    exit;

});




$app->run();

 ?>