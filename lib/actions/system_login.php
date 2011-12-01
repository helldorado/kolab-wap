<?php
    if (!$_SERVER["REQUEST_METHOD"] == "POST") {
        throw new Exception("You are not posting any information you twat!");
    }
?>
