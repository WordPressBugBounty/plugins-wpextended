<?php
if (!defined('ABSPATH')) {
    die();
}

require_once $this->getPath('layouts/header.php', true);

?>
<div class="wpextended__container">
    <?php require_once $this->getPath('layouts/content.php', true); ?>
</div>
</body>

</html>
