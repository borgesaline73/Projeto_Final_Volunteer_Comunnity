<?php
session_start();

if (!isset($_SESSION["teste"])) {
    $_SESSION["teste"] = "ok";
    echo "Sessão CRIADA agora. Recarregue a página.";
} else {
    echo "Sessão já EXISTE: " . $_SESSION["teste"];
}
