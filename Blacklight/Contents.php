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
    public function __construct() {}

    /**
     * @return array|false
     */
    public function get(): bool|array
    {
        $arr = [];
        $rows = $this->data_get();
        if ($rows === null) {
            return false;
        }

        foreach ($rows as $row) {
            $arr[] = $row;
        }

        return $arr;
    }

    /**
     * @return array|false
     */
    public function getAll(): bool|array
    {
        $arr = [];
        $rows = $this->data_getAll();
        if ($rows === null) {
            return false;
        }

        foreach ($rows as $row) {
            $arr[] = $row;
        }

        return $arr;
    }

    /**
     * Convert get all but from to object.
     *
     * @return array|false
     */
    public function getAllButFront(): bool|array
    {
        $arr = [];
        $rows = $this->data_getAllButFront();
        if ($rows === null) {
            return false;
        }

        foreach ($rows as $row) {
            $arr[] = $row;
        }

        return $arr;
    }

    /**
     * @return array|false
     */
    public function getFrontPage(): bool|array
    {
        $arr = [];
        $rows = $this->data_getFrontPage();
        if ($rows === null) {
            return false;
        }

        foreach ($rows as $row) {
            $arr[] = $row;
        }

        return $arr;
    }

    public function getIndex()
    {
        $row = $this->data_getIndex();

        return $row ?? false;
    }

    /**
     * @return false|mixed
     */
    public function getByID($id, $role): mixed
    {
        $row = $this->data_getByID($id, $role);

        if ($row === null) {
            return false;
        }

        return Arr::first($row);
    }

    public function validate($content): mixed
    {
        if ($content['url'] !== '/') {
            $content['url'] = '/'.$content['url'];
        }

        if (! str_ends_with($content['url'], '/')) {
            $content['url'] .= '/';
        }

        return $content;
    }

    public function add($form): int
    {
        if ($form['ordinal'] === 1) {
            Content::query()->where('ordinal', '>', 0)->increment('ordinal');
        }

        return $this->data_add($form);
    }

    public function delete($id): mixed
    {
        return Content::query()->where('id', $id)->delete();
    }

    /**
     * @return mixed|Content
     */
    public function update($form): mixed
    {
        $this->data_update($form);

        return $form;
    }

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
                    'status' => $content['status'],
                    'ordinal' => $content['ordinal'],
                    'updated_at' => now(),
                ]
            );
    }

    public function data_add($content): int
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
                    'status' => $content['status'],
                    'ordinal' => $content['ordinal'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
    }

    /**
     * @return Content[]|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Query\Builder[]|\Illuminate\Support\Collection
     */
    public function data_get(): array|\Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
    {
        return Content::query()
            ->where('status', '=', 1)
            ->orderByRaw('contenttype, COALESCE(ordinal, 1000000)')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function data_getAll(): \Illuminate\Database\Eloquent\Collection|static
    {
        return Content::query()->select()->orderByRaw('contenttype, COALESCE(ordinal, 1000000)')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function data_getAllButFront(): \Illuminate\Database\Eloquent\Collection|static
    {
        return Content::query()
            ->where('id', '<>', 1)
            ->orderByRaw('contenttype, COALESCE(ordinal, 1000000)')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function data_getByID($id, $role): \Illuminate\Database\Eloquent\Collection|static
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
    public function data_getFrontPage(): \Illuminate\Database\Eloquent\Collection|static
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

    public function data_getIndex(): ?Content
    {
        return Content::query()->where(
            [
                'status' => 1,
                'contenttype' => self::TYPEINDEX,
            ]
        )->first();
    }
}
