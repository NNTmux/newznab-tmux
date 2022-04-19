<?php

namespace Blacklight;

use App\Models\Content;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * Class Contents.
 */
class Contents
{
    public const TYPEUSEFUL = 1;
    public const TYPEARTICLE = 2;
    public const TYPEINDEX = 3;

    /**
     * Contents constructor.
     */
    public function __construct()
    {
    }

    /**
     * @return array|false
     */
    public function get()
    {
        $arr = [];
        $rows = $this->data_get();
        if ($rows === null) {
            return false;
        }

        return array_values($rows);
    }

    /**
     * @return array|false
     */
    public function getAll()
    {
        $arr = [];
        $rows = $this->data_getAll();
        if ($rows === null) {
            return false;
        }

        return array_values($rows);
    }

    /**
     * Convert get all but from to object.
     *
     * @return array|false
     */
    public function getAllButFront()
    {
        $arr = [];
        $rows = $this->data_getAllButFront();
        if ($rows === null) {
            return false;
        }

        return array_values($rows);
    }

    /**
     * @return array|false
     */
    public function getFrontPage()
    {
        $arr = [];
        $rows = $this->data_getFrontPage();
        if ($rows === null) {
            return false;
        }

        return array_values($rows);
    }

    /**
     * @param $id
     * @param $role
     * @return array|false
     */
    public function getForMenuByTypeAndRole($id, $role)
    {
        $arr = [];
        $rows = $this->data_getForMenuByTypeAndRole($id, $role);
        if ($rows === false) {
            return false;
        }

        return array_values($rows);
    }

    /**
     * @return \Blacklight\Contents|bool|\Illuminate\Database\Eloquent\Model|null|object
     */
    public function getIndex()
    {
        $row = $this->data_getIndex();
        if ($row === null) {
            return false;
        }

        return $row;
    }

    /**
     * @param $id
     * @param $role
     * @return bool|\Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getByID($id, $role)
    {
        $row = $this->data_getByID($id, $role);

        if ($row === null) {
            return false;
        }

        return Arr::first($row);
    }

    /**
     * @param $content
     * @return mixed
     */
    public function validate($content)
    {
        if ($content['url'] !== '/') {
            $content['url'] = '/'.$content['url'];
        }

        if (substr($content['url'], \strlen($content['url']) - 1) !== '/') {
            $content['url'] .= '/';
        }

        return $content;
    }

    /**
     * @param $form
     * @return int
     */
    public function add($form)
    {
        if ($form['ordinal'] === 1) {
            Content::query()->where('ordinal', '>', 0)->increment('ordinal');
        }

        return $this->data_add($form);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function delete($id)
    {
        return Content::query()->where('id', $id)->delete();
    }

    /**
     * @param $form
     * @return mixed|Content
     */
    public function update($form)
    {
        $this->data_update($form);

        return $form;
    }

    /**
     * @param $content
     * @return int
     */
    public function data_update($content): int
    {
        return Content::query()
            ->where('id', $content['id'])
            ->update(
                [
                    'role' => $content['role'],
                    'title' => $content['title'],
                    'url' => $content['url'],
                    'body' => $content['body'],
                    'metadescription' => $content['metadescription'],
                    'metakeywords' => $content['metakeywords'],
                    'contenttype' => $content['contenttype'],
                    'showinmenu' => $content['showinmenu'],
                    'status' => $content['status'],
                    'ordinal' => $content['ordinal'],
                ]
            );
    }

    /**
     * @param $content
     * @return int
     */
    public function data_add($content)
    {
        return Content::query()
            ->insertGetId(
                [
                    'role' => $content['role'],
                    'title' => $content['title'],
                    'url' => $content['url'],
                    'body' => $content['body'],
                    'metadescription' => $content['metadescription'],
                    'metakeywords' => $content['metakeywords'],
                    'contenttype' => $content['contenttype'],
                    'showinmenu' => $content['showinmenu'],
                    'status' => $content['status'],
                    'ordinal' => $content['ordinal'],
                ]
            );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function data_get()
    {
        return Content::query()
            ->where('status', '=', 1)
            ->orderByRaw('contenttype, COALESCE(ordinal, 1000000)')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function data_getAll()
    {
        return Content::query()->select()->orderByRaw('contenttype, COALESCE(ordinal, 1000000)')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function data_getAllButFront()
    {
        return Content::query()
            ->where('id', '<>', 1)
            ->orderByRaw('contenttype, COALESCE(ordinal, 1000000)')
            ->get();
    }

    /**
     * @param $id
     * @param $role
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function data_getByID($id, $role)
    {
        $query = Content::query()->where('id', $id);
        if ($role !== User::ROLE_ADMIN) {
            $query->where('role', $role)->orWhere('role', '=', 0);
        }

        return $query->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function data_getFrontPage()
    {
        return Content::query()
            ->where(
                [
                    'status' => 1,
                    'contenttype' => self::TYPEINDEX,
                ]
            )
            ->orderByRaw('ordinal ASC, COALESCE(ordinal, 1000000), id')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
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
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function data_getForMenuByTypeAndRole($id, $role)
    {
        $query = Content::query()->where('showinmenu', '=', 1)->where('contenttype', $id)->where('status', '=', 1);
        if ($role !== User::ROLE_ADMIN) {
            $query->where('role', $role)->orWhere('role', '=', 0);
        }

        return $query->get();
    }
}
