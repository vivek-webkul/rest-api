<?php

namespace Webkul\RestApi\Http\Controllers\V1\Admin\Catalog;

use Illuminate\Support\Facades\Event;
use Illuminate\Http\Request;
use Webkul\Category\Http\Requests\CategoryRequest;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Core\Http\Requests\MassDestroyRequest;
use Webkul\Core\Models\Channel;
use Webkul\RestApi\Http\Resources\V1\Admin\Catalog\CategoryResource;

class CategoryController extends CatalogController
{
    /**
     * Repository class name.
     *
     * @return string
     */
    public function repository()
    {
        return CategoryRepository::class;
    }

    /**
     * Resource class name.
     *
     * @return string
     */
    public function resource()
    {
        return CategoryResource::class;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Webkul\Category\Http\Requests\CategoryRequest  $categoryRequest
     * @return \Illuminate\Http\Response
     */
    public function store(CategoryRequest $categoryRequest)
    {
        Event::dispatch('catalog.category.create.before');

        $category = $this->getRepositoryInstance()->create($categoryRequest->all());

        Event::dispatch('catalog.category.create.after', $category);

        return response([
            'data'    => new CategoryResource($category),
            'message' => __('rest-api::app.common-response.success.create', ['name' => 'Category']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Webkul\Category\Http\Requests\CategoryRequest  $categoryRequest
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(CategoryRequest $categoryRequest, $id)
    {
        $this->getRepositoryInstance()->findOrFail($id);
        
        Event::dispatch('catalog.category.update.before', $id);

        $category = $this->getRepositoryInstance()->update($categoryRequest->all(), $id);

        Event::dispatch('catalog.category.update.after', $category);

        return response([
            'data'    => new CategoryResource($category),
            'message' => __('rest-api::app.common-response.success.update', ['name' => 'Category']),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $category = $this->getRepositoryInstance()->findOrFail($id);

        if (! $this->isCategoryDeletable($category)) {
            return response([
                'message' => __('rest-api::app.common-response.error.root-category-delete', ['name' => 'Category']),
            ], 400);
        }

        Event::dispatch('catalog.category.delete.before', $id);

        $this->getRepositoryInstance()->delete($id);

        Event::dispatch('catalog.category.delete.after', $id);

        return response([
            'message' => __('rest-api::app.common-response.success.delete', ['name' => 'Category']),
        ]);
    }

    /**
     * Remove the specified resources from database.
     *
     * @param  \Webkul\Core\Http\Requests\MassDestroyRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function massDestroy(MassDestroyRequest $request)
    {
        $categories = $this->getRepositoryInstance()->findWhereIn('id', $request->indexes);

        if ($this->containsNonDeletableCategory($categories)) {
            return response([
                'message' => __('rest-api::app.common-response.error.root-category-delete', ['name' => 'Category']),
            ], 400);
        }

        $categories->each(function ($category) {

            Event::dispatch('catalog.category.delete.before', $category->id);

            $this->getRepositoryInstance()->delete($category->id);

            Event::dispatch('catalog.category.delete.after', $category->id);
            
        });

        return response([
            'message' => __('rest-api::app.common-response.success.mass-operations.delete', ['name' => 'categories']),
        ]);
    }

    /**
     * Check whether the current category is deletable or not.
     *
     * This method will fetch all root category ids from the channel. If `id` is present,
     * then it is not deletable.
     *
     * @param  \Webkul\Category\Models\Category  $category
     * @return bool
     */
    private function isCategoryDeletable($category)
    {
        static $rootIdInChannels;

        if (! $rootIdInChannels) {
            $rootIdInChannels = Channel::pluck('root_category_id');
        }

        return ! ($category->id === 1 || $rootIdInChannels->contains($category->id));
    }

    /**
     * Check whether indexes contains non deletable category or not.
     *
     * @param  \Kalnoy\Nestedset\Collection  $categoryIds
     * @return bool
     */
    private function containsNonDeletableCategory($categories)
    {
        return $categories->contains(function ($category) {
            return ! $this->isCategoryDeletable($category);
        });
    }
}
