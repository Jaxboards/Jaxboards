<?php

$meta = array(
    'members-table' => <<<'EOT'
<table>
    <tr>
        <th>
        </th>
        <th>
            %1$s / %2$s
        </th>
        <th>
            %3$s
        </th>
        <th>
            %4$s
        </th>
        <th>
            Join Date
        </th>
        <th>
            Contact
        </th>
    </tr>
    %5$s
</table>
EOT
    ,
    'members-row' => <<<'EOT'
<tr>
    <td class="avatar">
        <a href="?act=vu%1$s">
            <img src="%2$s" alt="Avatar" />
        </a>
    </td>
    <td>
        %3$s
        <br />
        %4$s
    </td>
    <td>
        #%5$s
    </td>
    <td>
        %6$s
    </td>
    <td>
        %7$s
    </td>
    <td>
        %8$s
    </td>
</tr>
EOT
    ,
);
