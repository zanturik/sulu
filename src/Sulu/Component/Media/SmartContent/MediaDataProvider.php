<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\Media\SmartContent;

use Sulu\Bundle\MediaBundle\Admin\MediaAdmin;
use Sulu\Bundle\MediaBundle\Collection\Manager\CollectionManagerInterface;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Serializer\ArraySerializerInterface;
use Sulu\Component\SmartContent\DataProviderResult;
use Sulu\Component\SmartContent\DatasourceItem;
use Sulu\Component\SmartContent\Orm\BaseDataProvider;
use Sulu\Component\SmartContent\Orm\DataProviderRepositoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Media DataProvider for SmartContent.
 */
class MediaDataProvider extends BaseDataProvider
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var CollectionManagerInterface
     */
    private $collectionManager;

    public function __construct(
        DataProviderRepositoryInterface $repository,
        CollectionManagerInterface $collectionManager,
        ArraySerializerInterface $serializer,
        RequestStack $requestStack,
        ReferenceStoreInterface $referenceStore,
        TokenStorageInterface $tokenStorage = null
    ) {
        parent::__construct($repository, $serializer, $referenceStore, $tokenStorage);

        $this->configuration = self::createConfigurationBuilder()
            ->enableTags()
            ->enableCategories()
            ->enableLimit()
            ->enablePagination()
            ->enablePresentAs()
            ->enableAudienceTargeting()
            ->enableDatasource('collections', 'collections', 'column_list')
            ->enableSorting(
                [
                    ['column' => 'fileVersionMeta.title', 'title' => 'sulu_admin.title'],
                ]
            )
            ->enableView(MediaAdmin::EDIT_FORM_VIEW, ['id' => 'id'])
            ->getConfiguration();

        $this->requestStack = $requestStack;
        $this->collectionManager = $collectionManager;
    }

    public function getDefaultPropertyParameter()
    {
        return [
            'mimetype_parameter' => new PropertyParameter('mimetype_parameter', 'mimetype', 'string'),
            'type_parameter' => new PropertyParameter('type_parameter', 'type', 'string'),
        ];
    }

    public function resolveDataItems(
        array $filters,
        array $propertyParameter,
        array $options = [],
        $limit = null,
        $page = 1,
        $pageSize = null
    ) {
        if (($filters['dataSource'] ?? null) === null) {
            return new DataProviderResult([], false);
        }

        return parent::resolveDataItems($filters, $propertyParameter, $options, $limit, $page, $pageSize);
    }

    public function resolveDatasource($datasource, array $propertyParameter, array $options)
    {
        if (empty($datasource)) {
            return;
        }

        if ('root' === $datasource) {
            $title = 'smart-content.media.all-collections';

            return new DatasourceItem('root', $title, $title);
        }

        $entity = $this->collectionManager->getById($datasource, $options['locale']);

        return new DatasourceItem($entity->getId(), $entity->getTitle(), $entity->getTitle());
    }

    public function resolveResourceItems(
        array $filters,
        array $propertyParameter,
        array $options = [],
        $limit = null,
        $page = 1,
        $pageSize = null
    ) {
        if (($filters['dataSource'] ?? null) === null) {
            return new DataProviderResult([], false);
        }

        return parent::resolveResourceItems($filters, $propertyParameter, $options, $limit, $page, $pageSize);
    }

    protected function getOptions(
        array $propertyParameter,
        array $options = []
    ) {
        $request = $this->requestStack->getCurrentRequest();

        $queryOptions = [];

        if (\array_key_exists('mimetype_parameter', $propertyParameter)) {
            $queryOptions['mimetype'] = $request->get($propertyParameter['mimetype_parameter']->getValue());
        }
        if (\array_key_exists('type_parameter', $propertyParameter)) {
            $queryOptions['type'] = $request->get($propertyParameter['type_parameter']->getValue());
        }

        return \array_merge($options, \array_filter($queryOptions));
    }

    protected function decorateDataItems(array $data)
    {
        return \array_map(
            function($item) {
                return new MediaDataItem($item);
            },
            $data
        );
    }

    protected function getSerializationContext()
    {
        $serializationContext = parent::getSerializationContext();

        $serializationContext->setGroups(['Default']);

        return $serializationContext;
    }
}
