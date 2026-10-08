<?php

declare(strict_types=1);

/** Site root: send visitors to the private dashboard unless a landing page replaces it. */
header('Location: dashboard/', true, 302);
