<?php

class kolab_admin_client_output
{
    private $tpl_vars = array();
    private $env = array();
    private $objects = array();
    private $commands = array();
    private $labels = array();
    private $skin;

    public function __construct($skin = null)
    {
        $this->skin = $skin ? $skin : 'default';
        $this->init();
    }

    public function init()
    {
        require_once 'Smarty/Smarty.class.php';

        $SMARTY = new Smarty;
        $SMARTY->template_dir = 'skins/' . $this->skin . '/templates';
        $SMARTY->compile_dir  = 'cache';
        $SMARTY->plugins_dir  = dirname(__FILE__) . '/Smarty/plugins/';
        $SMARTY->debugging    = false;

        $this->tpl = $SMARTY;
    }

    public function send($template = null)
    {
        if ($this->is_ajax()) {
            echo $this->send_json();
        }
        else {
            $this->send_tpl($template);
        }
    }

    private function send_json()
    {
        header('Content-Type: application/json');

        $response = array(
            'objects' => $this->objects,
            'env'     => array(),
        );

        foreach ($this->env as $name => $value) {
            $response['env'][$name] = $value;
        }

        foreach ($this->commands as $command) {
            $cname = array_shift($command);
            $args  = array();

            foreach ($command as $arg) {
                $args[] = json_encode($arg);
            }

            $commands[] = sprintf('kadm.%s(%s);', $cname, implode(',', $args));
        }

        if (!empty($commands)) {
            $response['exec'] = implode("\n", $commands);
        }

        $this->labels = array_unique($this->labels);
        foreach ($this->labels as $label) {
            $response['labels'][$label] = kolab_admin_client_task::translate($label);
        }

        return json_encode($response);
    }

    private function send_tpl($template)
    {
        if (!$template) {
            return;
        }

        foreach ($this->tpl_vars as $name => $value) {
            $this->tpl->assign($name, $value);
        }

        $script = '';

        if (!empty($this->env)) {
            $script[] = 'kadm.env = ' . json_encode($this->env) . ';';
        }

        $this->labels = array_unique($this->labels);
        if (!empty($this->labels)) {
            foreach ($this->labels as $label) {
                $labels[$label] = kolab_admin_client_task::translate($label);
            }
            $script[] = 'kadm.tdef(' . json_encode($labels) . ');';
        }

        foreach ($this->commands as $command) {
            $cname = array_shift($command);
            $args  = array();

            foreach ($command as $arg) {
                $args[] = json_encode($arg);
            }

            $script[] = sprintf('kadm.%s(%s);', $cname, implode(',', $args));
        }

        $this->tpl->assign('skin_path', 'skins/' . $this->skin . '/');
        if ($script) {
            $script = "<script type=\"text/javascript\">\n" . implode("\n", $script) . "\n</script>";
            $this->tpl->assign('script', $script);
        }

        $this->tpl->display($template . '.html');
    }

    public function is_ajax()
    {
        return !empty($_REQUEST['remote']);
    }

    public function assign($name, $value)
    {
        $this->tpl_vars[$name] = $value;
    }

    public function set_env($name, $value)
    {
        $this->env[$name] = $value;
    }

    public function set_object($name, $content, $is_template = false)
    {
        if ($is_template) {
            $content = $this->get_template($content);
        }

        $this->objects[$name] = $content;
    }

    public function get_template($name)
    {
        ob_start();
        $this->send_tpl($name);
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    public function command()
    {
        $this->commands[] = func_get_args();
    }

    public function add_translation()
    {
        $this->labels = array_merge($this->labels, func_get_args());
    }

    public static function escape($str)
    {
    
    }
}
