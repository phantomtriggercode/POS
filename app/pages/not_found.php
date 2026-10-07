<?php
layout_start('Not found');
echo '<div class="card"><h2>Page not found</h2><p><a href="' . e(url('dashboard')) . '">Back to the dashboard</a></p></div>';
layout_end();
