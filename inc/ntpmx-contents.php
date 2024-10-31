<?php

if (!defined('NTPMX_PLUGIN_DIR')) {
    define('NTPMX_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
require_once(NTPMX_PLUGIN_DIR . 'inc/ntpmx.class.php');
$ntp = new ntpmx_api();
$lbl_date_activation = $ntp->ntpmx_date_activation();
$query_args = ['page' => 'ntpmx-import-force', 'force' => 1];
$has_new_content = get_option('ntpmx_new_content');
$lbl_new_content="";
if ($has_new_content==1) {
    $lbl_new_content = "¡Hay " . $has_new_content . " nuevo contenido!";
}
else if ($has_new_content > 1) {
    if ($has_new_content>99){
        $lbl_new_content = "¡Hay más de 100 nuevos contenidos!";
    }
    else {
        $lbl_new_content = "¡Hay " . $has_new_content . " nuevos contenidos!";
    }
}
$link_force='<a class="ntpmx-btn" href="'. add_query_arg( $query_args, admin_url( 'admin.php')) . '">Actualizar contenidos</a>' . '&nbsp;&nbsp;<span style="color: #a32d00; font-weight: bold">' . $lbl_new_content . '</span>';
$customer_id = $ntp->ntpmx_customer_id;
$sku_name = $ntp->ntpmx_get_sku_name();
?>
<div class="ntpmx-container_banner">
    <div class="ntpmx_content_wrapper">
        <div class="ntpmx_content_cell" id="wpseo_content_top">
            <p><span class="ntpmx-service-box ntpmx-sku-<?=strtolower($sku_name)?>"> <?php echo $ntp->ntpmx_get_sku_name();?></span>&nbsp;<a href="javascript:viewSku('<?=strtolower($customer_id);?>','<?=strtolower($ntp->ntpmx_sku);?>');">¿Qué incluye?</a></p>
        </div>
        <div class="ntpmx_content_cell2" id="#ntpm-banner">
            <img alt='NotiPress Noticias' src="<?=plugins_url('notipress-noticias/img/logo.png')?>">
        </div>
    </div>
    <?php
    global $msg;
    global $data;
    if (isset($customer_id)) {
        $data = $ntp->get_message($ntp->ntpmx_customer_id);
    }
    if (is_array($data)){
        if (isset($data["intro"]) && strlen($data["intro"])>0){
        ?>
        <p><?php echo $data["intro"]; ?> <a href="javascript:viewMessage('<?=$ntp->ntpmx_customer_id?>','<?=$data["message_id"]?>');">Aprender más</a></p>
        <?php
        }
    }
    ?>
    <?php
    ?>

</div>
<div class="wrap">
    <p><?=$link_force ?></p>
    <?php echo $ntp->ntpmx_show_content_from_database()?>
</div>
<div>
    <p>Versión <?= NTPMX_VERSION . '<br>Activación: ' . $lbl_date_activation . ' (PHP ' . phpversion() . ')'?></p>
    <p>
        <?php
        $customer = $ntp->get_consumption($customer_id);
        if (is_array($customer)) {
            if (isset($customer["total_contents"])) {
                if ($customer["total_contents"] != -1) {
                    $total_contents = ($customer["total_contents"] == -1 || $customer["total_contents"] == 0) ? "250 " : $customer["total_contents"];
                    echo "<p>";
                    echo "Consumo del mes: ";
                    $total_unlocked = ($customer["total_unlocked"] == -1) ? "0 " : $customer["total_unlocked"];
                    echo $total_unlocked . ' de ' . $total_contents . " contenidos";
                    if (strtolower($ntp->ntpmx_get_sku()) == 'ntproconn') {
                        $total_photos = ($customer["total_photos"] == -1) ? "0 " : $customer["total_photos"];
                        $total_photos_unlocked = ($customer["total_photos_unlocked"] == -1) ? "0 " : $customer["total_photos_unlocked"];
                        echo " | Fotos del mes: " . $total_photos_unlocked . ' de ' . $total_photos;
                    } else if (strtolower($ntp->ntpmx_get_sku()) == 'nplan') {
                        echo "<br>";
                        echo "Fotos: El plan <strong>" . $sku_name . "</strong> no tiene acceso al banco fotográfico.";
                        echo "</p>";
                    }
                }
            }
        }
        ?>
    </p>
</div>
<script>
    function viewMessage(customer_id,message_id){
        var dualScreenLeft = window.screenLeft != undefined ? window.screenLeft : window.screenX;
        var dualScreenTop = window.screenTop != undefined ? window.screenTop : window.screenY;

        var width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width;
        var height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height;

        var systemZoom = width / window.screen.availWidth;
        var left = (width - 400) / 2 / systemZoom + dualScreenLeft
        var top = (height - 100) / 2 / systemZoom + dualScreenTop

        var newWindow = window.open("<?=$ntp->ntpmx_public_domain?>/apiv3/get-message?message_id="+message_id+'&customer_id='+customer_id,'Ver mensaje', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=400,width=600,' + 'top=' + top + ', left=' + left);
        if (window.focus) newWindow.focus();
        return false;
    }
    function viewSku(customer_id,sku){
        var dualScreenLeft = window.screenLeft != undefined ? window.screenLeft : window.screenX;
        var dualScreenTop = window.screenTop != undefined ? window.screenTop : window.screenY;

        var width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width;
        var height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height;

        var systemZoom = width / window.screen.availWidth;
        var left = (width - 400) / 2 / systemZoom + dualScreenLeft
        var top = (height - 100) / 2 / systemZoom + dualScreenTop

        var newWindow = window.open("<?=$ntp->ntpmx_private_domain?>/sku/view-description?sku="+sku+'&customer_id='+customer_id,'Ver descripción del plan', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=400,width=600,' + 'top=' + top + ', left=' + left);
        if (window.focus) newWindow.focus();
        return false;
    }
    function postLogin(){
        document.location.href='<?=$ntp->ntpmx_private_domain?>/login';
    }
</script>

