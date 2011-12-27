<?php
    function smarty_block_t($params, $content, $template, &$repeat)
    {
        if (!empty($content)) {

            array_unshift($params, $content);
            $content = kolab_client_task::translate($params);

            return trim($content);
        }
    }

?>
