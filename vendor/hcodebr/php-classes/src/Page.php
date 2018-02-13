<?php

namespace Hcode;

use Rain\Tpl;

class Page {

    private $tpl;
    private $options = [];
    private $default = [
        "data" => []
    ];

public function __construct($opt  = array(), $tpl_dir = "/views/"){

        $this->options = array_merge($this->default, $opt);
        $config = array(
                    "tpl_dir"       => $_SERVER["DOCUMENT_ROOT"].$tpl_dir,
                    "cache_dir"     => $_SERVER["DOCUMENT_ROOT"]."/views-cache/",
                    "debug"         => false
                   );

        Tpl::configure($config);

        $this->tpl = new Tpl;

        $this->setData($this->options["data"]);

        $this->tpl->draw("header");

}

public function setData ($data  = array()){
     foreach ($this->options["data"] as $key => $values){
            $this->tpl->assign($key, $values);
        }
}

public function setTpl($name, $data  = array(), $returnHtml = false)
{

     $this->setData($data);

     $this->tpl->draw($name, $returnHtml);


}

public function __destruct(){

    $this->tpl->draw("footer");

}

}

?>