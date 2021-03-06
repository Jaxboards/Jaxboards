<?php

new downloader();
class downloader
{
    public function __construct()
    {
        global $JAX,$DB;
        $id = $JAX->b['id'];
        if (is_numeric($id)) {
            $result = $DB->safeselect(
                <<<'EOT'
`id`,`name`,`hash`,`uid`,`size`,`downloads`,INET6_NTOA(`ip`) AS `ip`
EOT
                ,
                'files',
                'WHERE `id`=?',
                $id
            );
            $data = $DB->arow($result);
            $DB->disposeresult($result);
        }
        if ($data) {
            $DB->safespecial(
                <<<'EOT'
UPDATE %t
SET `downloads` = `downloads` + 1
WHERE `id`=?
EOT
                ,
                array('files'),
                $id
            );
            $ext = explode('.', $data['name']);
            if (1 == count($ext)) {
                $ext = '';
            } else {
                $ext = mb_strtolower(array_pop($ext));
            }
            if (in_array($ext, array('jpeg', 'jpg', 'png', 'gif', 'bmp'))) {
                $data['hash'] .= '.' . $ext;
            }
            $file = BOARDPATH . 'Uploads/' . $data['hash'];
            if (file_exists($file)) {
                if (!$data['name']) {
                    $data['name'] = 'unknown';
                }
                header('Content-type:application/idk');
                header(
                    'Content-disposition:attachment;filename="' .
                    $data['name'] . '"'
                );
                readfile($file);
            }
            die();
        }
    }
}
