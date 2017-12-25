<?php

namespace nntmux;

use App\Models\User;
use nntmux\db\DB;

/**
 * This class looks up site menu data.
 */
class Menu
{
    /**
     * @var \nntmux\db\DB
     */
    public $pdo;

    /**
     * @param \nntmux\db\DB $settings
     */
    public function __construct($settings = null)
    {
        $this->pdo = ($settings instanceof DB ? $settings : new DB());
    }

    /**
     * @param $role
     * @param $serverurl
     *
     * @return array
     */
    public function get($role, $serverurl)
    {
        $guest = '';
        if ($role !== User::ROLE_GUEST) {
            $guest = sprintf(' AND role != %d ', User::ROLE_GUEST);
        }

        if ($role !== User::ROLE_ADMIN) {
            $guest .= sprintf(' AND role != %d ', User::ROLE_ADMIN);
        }

        $data = $this->pdo->query(sprintf('SELECT * FROM menu WHERE role <= %d %s ORDER BY ordinal', $role, $guest));

        $ret = [];
        foreach ($data as $d) {
            if (stripos($d['href'], 'http') === false) {
                $d['href'] = $serverurl.$d['href'];
                $ret[] = $d;
            } else {
                $ret[] = $d;
            }
        }

        return $ret;
    }

    public function getAll()
    {
        return $this->pdo->query('SELECT * FROM menu ORDER BY role, ordinal');
    }

    public function getById($id)
    {
        return $this->pdo->queryOneRow(sprintf('SELECT * FROM menu WHERE id = %d', $id));
    }

    public function delete($id)
    {
        return $this->pdo->queryExec(sprintf('DELETE FROM menu WHERE id = %d', $id));
    }

    public function add($menu)
    {
        return $this->pdo->queryInsert(sprintf('INSERT INTO menu (href, title, tooltip, role, ordinal, menueval, newwindow ) VALUES (%s, %s,  %s, %d, %d, %s, %d)', $this->pdo->escapeString($menu['href']), $this->pdo->escapeString($menu['title']), $this->pdo->escapeString($menu['tooltip']), $menu['role'], $menu['ordinal'], $this->pdo->escapeString($menu['menueval']), $menu['newwindow']));
    }

    public function update($menu)
    {
        return $this->pdo->queryExec(sprintf('UPDATE menu SET href = %s, title = %s, tooltip = %s, role = %d, ordinal = %d, menueval = %s, newwindow = %d WHERE id = %d', $this->pdo->escapeString($menu['href']), $this->pdo->escapeString($menu['title']), $this->pdo->escapeString($menu['tooltip']), $menu['role'], $menu['ordinal'], $this->pdo->escapeString($menu['menueval']), $menu['newwindow'], $menu['id']));
    }
}
