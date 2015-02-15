<?php

if (copy('!htaccess', '.htaccess')) {
    echo 'Installation successfull: .htacces copied, app selected: default. Refresh page.';
} else {
    echo 'Installation failed. Make sure PHP is set-up correctly.';
}
