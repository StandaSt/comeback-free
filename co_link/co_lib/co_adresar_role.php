<?php
/* Co-Link: pripravi skupiny manager a vedouci a zalozni nazvy roli. */
declare(strict_types=1);

/** Manager zahrnuje role 1,2,3; vedouci pobocky ma samostatnou roli 5. */
function co_adresar_role(array $rows): array
{
    $groups = []; $labels = [];
    foreach ($rows as $r) {
        $role = (int)$r['id_role']; $person = (int)$r['id_person'];
        $labels[$person][] = $r['role'];
        if (in_array($role, [1,2,3], true)) $groups[$person]['manager'] = true;
        if ($role === 5) $groups[$person]['vedouci'] = true;
    }
    return ['skupiny' => $groups, 'nazvy' => $labels];
}
