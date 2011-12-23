<?php
    function smarty_function_gentime($params, $template)
    {
        $time = microtime(true);
        return sprintf('%.4f', $time - KADM_START);
    }
?>
