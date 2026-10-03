<?php
/** Run from a scheduler: php backup.php --destination path/to/backups */
declare(strict_types=1);
$args=getopt('', ['destination:']); $destination=$args['destination'] ?? (__DIR__.'/data/backups');
if (!is_dir($destination) && !mkdir($destination,0700,true) && !is_dir($destination)) throw new RuntimeException('Cannot create backup directory.');
foreach (glob(__DIR__.'/data/*.db') ?: [] as $source) { $db=new SQLite3($source); $db->busyTimeout(5000); $db->exec('PRAGMA wal_checkpoint(FULL)'); $db->close(); $target=rtrim($destination,'/\\').DIRECTORY_SEPARATOR.basename($source,'.db').'-'.gmdate('Ymd-His').'.db'; if (!copy($source,$target)) throw new RuntimeException('Backup failed for '.basename($source)); chmod($target,0600); echo "Saved $target\n"; }
