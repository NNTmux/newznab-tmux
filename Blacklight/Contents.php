<?php

namespace Blacklight;

use Blacklight\db\DB;
use App\Models\User;
use App\Models\Content;

class Contents
{
    public const TYPEUSEFUL = 1;
    public const TYPEARTICLE = 2;
    public const TYPEINDEX = 3;

    /**
     * @var \Blacklight\db\DB
     */
    public $pdo;

    /**
     * @param array $options Class instances.
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Settings' => null,
        ];
        $options += $defaults;

        $this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
    }

    /**
     * @return array|bool
     */
    public function get()
    {
        $arr = [];
        $rows = $this->data_get();
        if ($rows === false) {
            return false;
        }

        foreach ($rows as $row) {
            $arr[] = $this->row2Object($row);
        }

        return $arr;
    }

    /**
     * @return array|bool
     */
    public function getAll()
    {
        $arr = [];
        $rows = $this->data_getAll();
        if ($rows === false) {
            return false;
        }

        foreach ($rows as $row) {
            $arr[] = $this->row2Object($row);
        }

        return $arr;
    }

    /**
     * Convert get all but from to object.
     *
     * @return array|bool
     */
    public function getAllButFront()
    {
        $arr = [];
        $rows = $this->data_getAllButFront();
        if ($rows === false) {
            return false;
        }

        foreach ($rows as $row) {
            $arr[] = $this->row2Object($row);
        }

        return $arr;
    }

    /**
     * @return array|bool
     */
    public function getFrontPage()
    {
        $arr = [];
        $rows = $this->data_getFrontPage();
        if ($rows === false) {
            return false;
        }

        foreach ($rows as $row) {
            $arr[] = $this->row2Object($row);
        }

        return $arr;
    }

    /**
     * @param $id
     * @param $role
     *
     * @return array|bool
     */
    public function getForMenuByTypeAndRole($id, $role)
    {
        $arr = [];
        $rows = $this->data_getForMenuByTypeAndRole($id, $role);
        if ($rows === false) {
            return false;
        }

        foreach ($rows as $row) {
            $arr[] = $this->row2Object($row);
        }

        return $arr;
    }

    /**
     * @return bool|Content
     */
    public function getIndex()
    {
        $row = $this->data_getIndex();
        if ($row === false) {
            return false;
        }

        return $this->row2Object($row);
    }

    /**
     * @param $id
     * @param $role
     *
     * @return bool|Content
     */
    public function getByID($id, $role)
    {
        $row = $this->data_getByID($id, $role);
        if ($row === false) {
            return false;
        }

        return $this->row2Object($row);
    }

    /**
     * @param $content
     *
     * @return mixed
     */
    public function validate($content)
    {
        if ($content->url !== '/') {
            $content->url = '/'.$content->url;
        }

        if (substr($content->url, \strlen($content->url) - 1) !== '/') {
            $content->url .= '/';
        }

        return $content;
    }

    /**
     * @param $form
     *
     * @return false|int|string
     */
    public function add($form)
    {
        $content = $this->row2Object($form);
        $content = $this->validate($content);
        if ($content->ordinal === 1) {
            Content::query()->where('ordinal', '>', 0)->increment('ordinal');
        }

        return $this->data_add($content);
    }

    /**
     * @param $id
     *
     * @return bool|\PDOStatement
     */
    public function delete($id)
    {
        return Content::query()->where('id', $id)->delete();
    }

    /**
     * @param $form
     *
     * @return mixed|Content
     */
    public function update($form)
    {
        $content = $this->row2Object($form);
        $content = $this->validate($content);
        $this->data_update($content);

        return $content;
    }

    /**
     * @param        $row
     * @param string $prefix
     *
     * @return Content
     */
    public function row2Object($row, $prefix = '')
    {
        $obj = new Content();
        if (isset($row[$prefix.'id'])) {
            $obj->id = $row[$prefix.'id'];
        }
        $obj->title = $row[$prefix.'title'];
        $obj->url = $row[$prefix.'url'];
        $obj->body = $row[$prefix.'body'];
        $obj->metadescription = $row[$prefix.'metadescription'];
        $obj->metakeywords = $row[$prefix.'metakeywords'];
        $obj->contenttype = $row[$prefix.'contenttype'];
        $obj->showinmenu = $row[$prefix.'showinmenu'];
        $obj->status = $row[$prefix.'status'];
        $obj->ordinal = $row[$prefix.'ordinal'];
        if (isset($row[$prefix.'created_at'])) {
            $obj->created_at = $row[$prefix.'created_at'];
        }
        $obj->role = $row[$prefix.'role'];

        return $obj;
    }

    /**
     * @param $content
     * @return int
     */
    public function data_update($content): int
    {
        return Content::query()
            ->where('id', $content->id)
            ->update(
                [
                    'role' => $content->role,
                    'title' => $content->title,
                    'url' => $content->url,
                    'body' => $content->body,
                    'metadescription' => $content->metadescription,
                    'metakeywords' => $content->metakeywords,
                    'contenttype' => $content->contenttype,
                    'showinmenu' => $content->showinmenu,
                    'status' => $content->status,
                    'ordinal' => $content->ordinal,
                ]
            );
    }

    /**
     * @param $content
     *
     * @return false|int|string
     */
    public function data_add($content)
    {
        return Content::query()
            ->insertGetId(
                [
                    'role' => $content->role,
                    'title' => $content->title,
                    'url' => $content->url,
                    'body' => $content->body,
                    'metadescription' => $content->metadescription,
                    'metakeywords' => $content->metakeywords,
                    'contenttype' => $content->contenttype,
                    'showinmenu' => $content->showinmenu,
                    'status' => $content->status,
                    'ordinal' => $content->ordinal,
                ]
            );
    }

    /**
     * @return array
     */
    public function data_get(): array
    {
        return Content::query()
            ->where('status', '=', 1)
            ->orderByRaw('contenttype, COALESCE(ordinal, 1000000)')
            ->get()
            ->all();
    }

    /**
     * @return array
     */
    public function data_getAll(): array
    {
        return Content::query()->select()->orderByRaw('contenttype, COALESCE(ordinal, 1000000)')->get()->all();
    }

    /**
     * Get all but front page.
     *
     * @return array
     */
    public function data_getAllButFront(): array
    {
        return Content::query()
            ->where('id', '!=', 1)
            ->orderByRaw('contenttype, COALESCE(ordinal, 1000000)')
            ->get()
            ->all();
    }

    /**
     * @param $id
     * @param $role
     *
     * @return array|bool
     */
    public function data_getByID($id, $role)
    {
        if ($role === User::ROLE_ADMIN) {
            $role = '';
        } else {
            $role = sprintf('AND (role = %d OR role = 0)', $role);
        }

        return $this->pdo->queryOneRow(sprintf('SELECT * FROM content WHERE id = %d %s', $id, $role));
    }

    /**
     * @return array
     */
    public function data_getFrontPage(): array
    {
        return Content::query()
            ->where(
                [
                    'status' => 1,
                    'contenttype' => self::TYPEINDEX,
                ]
            )
            ->orderByRaw('ordinal ASC, COALESCE(ordinal, 1000000), id')
            ->get()
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function data_getIndex()
    {
        return Content::query()->where(
            [
                'status' => 1,
                'contenttype' => self::TYPEINDEX,
            ]
        )->first();
    }

    /**
     * @param $id
     * @param $role
     *
     * @return array
     */
    public function data_getForMenuByTypeAndRole($id, $role): array
    {
        if ($role === User::ROLE_ADMIN) {
            $role = '';
        } else {
            $role = sprintf('AND (role = %d OR role = 0)', $role);
        }

        return $this->pdo->query(sprintf('SELECT * FROM content WHERE showinmenu = 1 AND status = 1 AND contenttype = %d %s ', $id, $role));
    }
}
