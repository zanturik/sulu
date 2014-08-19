<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\Content;

use DateTime;
use Sulu\Component\Content\Section\SectionPropertyInterface;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;

/**
 * Structure generated from Structure Manager to map a template.
 * This class is a blueprint of Subclasses generated by StructureManager. This sub classes will be cached in Symfony Cache
 */
abstract class Structure implements StructureInterface
{
    /**
     * indicates that the node is a content node
     */
    const NODE_TYPE_CONTENT = 1;

    /**
     * indicates that the node links to an internal resource
     */
    const NODE_TYPE_INTERNAL_LINK = 2;

    /**
     * indicates that the node links to an external resource
     */
    const NODE_TYPE_EXTERNAL_LINK = 4;

    /**
     * webspaceKey of node
     * @var string
     */
    private $webspaceKey;

    /**
     * languageCode of node
     * @var string
     */
    private $languageCode;

    /**
     * unique key of template
     * @var string
     */
    private $key;

    /**
     * real template from database
     * @var string
     */
    private $originTemplate;

    /**
     * template to render content
     * @var string
     */
    private $view;

    /**
     * controller to render content
     * @var string
     */
    private $controller;

    /**
     * time to cache content
     * @var int
     */
    private $cacheLifeTime;

    /**
     * array of properties
     * @var array
     */
    private $properties = array();

    /**
     * has structure sub structures
     * @var bool
     */
    private $hasChildren = false;

    /**
     * children of node
     * @var StructureInterface[]
     */
    private $children = null;

    /**
     * uuid of node in CR
     * @var string
     */
    private $uuid;

    /**
     * absolute path of node
     * @var string
     */
    private $path;

    /**
     * user id of creator
     * @var int
     */
    private $creator;

    /**
     * user id of changer
     * @var int
     */
    private $changer;

    /**
     * datetime of creation
     * @var DateTime
     */
    private $created;

    /**
     * datetime of last changed
     * @var DateTime
     */
    private $changed;

    /**
     * state of node
     * @var int
     */
    private $nodeState;

    /**
     * global state of node (with inheritance)
     * @var int
     */
    private $globalState;

    /**
     * first published
     * @var DateTime
     */
    private $published;

    /**
     * defines in which navigation context assigned
     * @var string[]
     */
    private $navContexts;

    /**
     * structure translation is valid
     * @var boolean
     */
    private $hasTranslation;

    /**
     * @var StructureType
     */
    private $type;

    /**
     * @var array
     */
    private $tags = array();

    /**
     * @var array
     */
    private $ext = array();

    /**
     * type of node
     * @var integer
     */
    private $nodeType;

    /**
     * indicates internal structure
     * @var boolean
     */
    private $internal;

    /**
     * content node that holds the internal link
     * @var StructureInterface
     */
    private $internalLinkContent;

    /**
     * content node is a shadow for another content
     * @var boolean
     */
    private $isShadow;

    /**
     * when shadow is enabled, this node is a shadow for
     * this language
     * @var string
     */
    private $shadowBaseLanguage = '';

    /**
     * the shadows which are activated on this node. Note this is
     * not stored in the phpcr node, it is determined by the content mapper.
     * @var array
     */
    private $enabledShadowLanguages = array();

    /**
     * @var array
     */
    private $concreteLanguages = array();

    /**
     * @var Metadata
     */
    private $metaData;

    /**
     * @param $key string
     * @param $view string
     * @param $controller string
     * @param int $cacheLifeTime
     * @param array $metaData
     * @return \Sulu\Component\Content\Structure
     */
    public function __construct($key, $view, $controller, $cacheLifeTime = 604800, $metaData = array())
    {
        $this->key = $key;
        $this->view = $view;
        $this->controller = $controller;
        $this->cacheLifeTime = $cacheLifeTime;
        $this->metaData = new Metadata($metaData);

        // default state is test
        $this->nodeState = StructureInterface::STATE_TEST;
        $this->published = null;

        // default hide in navigation
        $this->navContexts = array();

        // default content node-type
        $this->nodeType = self::NODE_TYPE_CONTENT;
    }

    /**
     * adds a property to structure
     * @param PropertyInterface $property
     */
    protected function addChild(PropertyInterface $property)
    {
        if ($property instanceof SectionPropertyInterface) {
            foreach ($property->getChildProperties() as $childProperty) {
                $this->addPropertyTags($childProperty);
            }
        } else {
            $this->addPropertyTags($property);
        }

        $this->properties[$property->getName()] = $property;
    }

    /**
     * add tags of properties
     */
    protected function addPropertyTags(PropertyInterface $property)
    {
        foreach ($property->getTags() as $tag) {
            if (!array_key_exists($tag->getName(), $this->tags)) {
                $this->tags[$tag->getName()] = array(
                    'tag' => $tag,
                    'properties' => array($tag->getPriority() => $property),
                    'highest' => $property,
                    'lowest' => $property
                );
            } else {
                $this->tags[$tag->getName()]['properties'][$tag->getPriority()] = $property;

                // replace highest priority property
                $highestProperty = $this->tags[$tag->getName()]['highest'];
                if ($highestProperty->getTag($tag->getName())->getPriority() < $tag->getPriority()) {
                    $this->tags[$tag->getName()]['highest'] = $property;
                }

                // replace lowest priority property
                $lowestProperty = $this->tags[$tag->getName()]['lowest'];
                if ($lowestProperty->getTag($tag->getName())->getPriority() > $tag->getPriority()) {
                    $this->tags[$tag->getName()]['lowest'] = $property;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getExt()
    {
        return $this->ext;
    }

    /**
     * {@inheritdoc}
     */
    public function setExt($data)
    {
        $this->ext = $data;
    }

    /**
     * @param string $language
     */
    public function setLanguageCode($language)
    {
        $this->languageCode = $language;
    }

    /**
     * returns language of node
     * @return string
     */
    public function getLanguageCode()
    {
        return $this->languageCode;
    }

    /**
     * @param string $webspace
     */
    public function setWebspaceKey($webspace)
    {
        $this->webspaceKey = $webspace;
    }

    /**
     * returns webspace of node
     * @return string
     */
    public function getWebspaceKey()
    {
        return $this->webspaceKey;
    }

    /**
     * key of template definition
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getOriginTemplate()
    {
        return $this->originTemplate;
    }

    /**
     * @param string $originTemplate
     */
    public function setOriginTemplate($originTemplate)
    {
        $this->originTemplate = $originTemplate;
    }

    /**
     * twig template of template definition
     * @return string
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * controller which renders the template definition
     * @return string
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * cacheLifeTime of template definition
     * @return int
     */
    public function getCacheLifeTime()
    {
        return $this->cacheLifeTime;
    }

    /**
     * returns uuid of node
     * @return string
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * sets uuid of node
     * @param $uuid
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * returns id of creator
     * @return int
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * sets user id of creator
     * @param $userId int id of creator
     */
    public function setCreator($userId)
    {
        $this->creator = $userId;
    }

    /**
     * returns user id of changer
     * @return int
     */
    public function getChanger()
    {
        return $this->changer;
    }

    /**
     * sets user id of changer
     * @param $userId int id of changer
     */
    public function setChanger($userId)
    {
        $this->changer = $userId;
    }

    /**
     * return created datetime
     * @return DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * sets created datetime
     * @param DateTime $created
     * @return \DateTime
     */
    public function setCreated(DateTime $created)
    {
        return $this->created = $created;
    }

    /**
     * returns changed DateTime
     * @return DateTime
     */
    public function getChanged()
    {
        return $this->changed;
    }

    /**
     * sets changed datetime
     * @param \DateTime $changed
     */
    public function setChanged(DateTime $changed)
    {
        $this->changed = $changed;
    }

    /**
     * returns a property instance with given name
     * @param $name string name of property
     * @return PropertyInterface
     * @throws NoSuchPropertyException
     */
    public function getProperty($name)
    {
        $result = $this->findProperty($name);

        if ($result !== null) {
            return $result;
        } elseif (isset($this->properties[$name])) {
            return $this->properties[$name];
        } else {
            throw new NoSuchPropertyException();
        }
    }

    /**
     * returns a property instance with given tag name
     * @param string $tagName
     * @param boolean $highest
     * @throws \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     * @return PropertyInterface
     */
    public function getPropertyByTagName($tagName, $highest = true)
    {
        if (array_key_exists($tagName, $this->tags)) {
            return $this->tags[$tagName][$highest === true ? 'highest' : 'lowest'];
        } else {
            throw new NoSuchPropertyException();
        }
    }

    /**
     * returns properties with given tag name sorted by priority
     * @param string $tagName
     * @throws \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     * @return PropertyInterface
     */
    public function getPropertiesByTagName($tagName)
    {
        if (array_key_exists($tagName, $this->tags)) {
            return $this->tags[$tagName]['properties'];
        } else {
            throw new NoSuchPropertyException();
        }
    }

    /**
     * return value of property with given name
     * @param $name string name of property
     * @return mixed
     */
    public function getPropertyValue($name)
    {
        return $this->getProperty($name)->getValue();
    }

    /**
     * returns value of property with given tag name
     * @param string $tagName
     * @return mixed
     */
    public function getPropertyValueByTagName($tagName)
    {
        return $this->getPropertyByTagName($tagName, true)->getValue();
    }

    /**
     * checks if a property exists
     * @param string $name
     * @return boolean
     */
    public function hasProperty($name)
    {
        return $this->findProperty($name) !== null;
    }

    /**
     * find property in flatten properties
     * @param string $name
     * @return null|PropertyInterface
     */
    private function findProperty($name)
    {
        foreach ($this->getProperties(true) as $property) {
            if ($property->getName() === $name) {
                return $property;
            }
        }

        return null;
    }

    /**
     * @param boolean $hasChildren
     */
    public function setHasChildren($hasChildren)
    {
        $this->hasChildren = $hasChildren;
    }

    /**
     * @return boolean
     */
    public function getHasChildren()
    {
        return $this->hasChildren;
    }

    /**
     * @param StructureInterface[] $children
     */
    public function setChildren($children)
    {
        $this->children = $children;
    }

    /**
     * @return null|StructureInterface[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param int $state
     * @return int
     */
    public function setNodeState($state)
    {
        $this->nodeState = $state;
    }

    /**
     * returns state of node
     * @return int
     */
    public function getNodeState()
    {
        return $this->nodeState;
    }

    /**
     * returns true if state of site is "published"
     * @return boolean
     */
    public function getPublishedState()
    {
        return ($this->nodeState === StructureInterface::STATE_PUBLISHED);
    }

    /**
     * sets the global state of node (with inheritance)
     * @param int $globalState
     */
    public function setGlobalState($globalState)
    {
        $this->globalState = $globalState;
    }

    /**
     * returns global state of node (with inheritance)
     * @return int
     */
    public function getGlobalState()
    {
        return $this->globalState;
    }

    /**
     * @param \DateTime $published
     */
    public function setPublished($published)
    {
        $this->published = $published;
    }

    /**
     * returns first published date
     * @return \DateTime
     */
    public function getPublished()
    {
        return $this->published;
    }

    /**
     * returns true if this node is shown in navigation
     * @return string[]
     */
    public function getNavContexts()
    {
        return $this->navContexts;
    }

    /**
     * @param string[] $navContexts
     */
    public function setNavContexts($navContexts)
    {
        $this->navContexts = $navContexts;
    }

    /**
     * @param boolean $hasTranslation
     */
    public function setHasTranslation($hasTranslation)
    {
        $this->hasTranslation = $hasTranslation;
    }

    /**
     * set if this structure should act like a shadow
     * @return boolean
     */
    public function getIsShadow() 
    {
        return $this->isShadow;
    }

    /**
     * set if this node should act like a shadow
     * @param boolean
     */
    public function setIsShadow($isShadow)
    {
        $this->isShadow = $isShadow;
    }

    /**
     * return the shadow base language
     * @return string
     */
    public function getShadowBaseLanguage() 
    {
        return $this->shadowBaseLanguage;
    }

    /**
     * set the shadow base language
     * @param string $shadowBaseLanguage
     */
    public function setShadowBaseLanguage($shadowBaseLanguage)
    {
        $this->shadowBaseLanguage = $shadowBaseLanguage;
    }

    /**
     * return true if structure translation is valid
     * @return boolean
     */
    public function getHasTranslation()
    {
        return $this->hasTranslation;
    }

    /**
     * returns an array of properties
     * @param bool $flatten
     * @return PropertyInterface[]
     */
    public function getProperties($flatten = false)
    {
        if ($flatten === false) {
            return $this->properties;
        } else {
            $result = array();
            foreach ($this->properties as $property) {
                if ($property instanceof SectionPropertyInterface) {
                    $result = array_merge($result, $property->getChildProperties());
                } else {

                    $result[] = $property;
                }
            }

            return $result;
        }
    }

    /**
     * returns all property names
     * @return array
     */
    public function getPropertyNames()
    {
        return array_keys($this->properties);
    }

    /**
     * @param \Sulu\Component\Content\StructureType $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return \Sulu\Component\Content\StructureType
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getNodeType()
    {
        return $this->nodeType;
    }

    /**
     * @param int $nodeType
     */
    public function setNodeType($nodeType)
    {
        $this->nodeType = $nodeType;
    }

    /**
     * @return boolean
     */
    public function getInternal()
    {
        return $this->internal;
    }

    /**
     * @param boolean $internal
     */
    public function setInternal($internal)
    {
        $this->internal = $internal;
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceLocator()
    {
        if (
            $this->getNodeType() === Structure::NODE_TYPE_INTERNAL_LINK &&
            $this->getInternalLinkContent() !== null &&
            $this->getInternalLinkContent()->hasTag('sulu.rlp')
        ) {
            return $this->getInternalLinkContent()->getPropertyValueByTagName('sulu.rlp');
        } elseif ($this->getNodeType() === Structure::NODE_TYPE_EXTERNAL_LINK) {
            // FIXME URL schema
            return 'http://' . $this->getPropertyByTagName('sulu.rlp')->getValue();
        } elseif ($this->hasTag('sulu.rlp')) {
            return $this->getPropertyValueByTagName('sulu.rlp');
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getNodeName()
    {
        if (
            $this->getNodeType() === Structure::NODE_TYPE_INTERNAL_LINK &&
            $this->getInternalLinkContent() !== null &&
            $this->getInternalLinkContent()->hasTag('sulu.node.name')
        ) {
            return $this->internalLinkContent->getPropertyValueByTagName('sulu.node.name');
        } elseif ($this->hasTag('sulu.node.name')) {
            return $this->getPropertyValueByTagName('sulu.node.name');
        }

        return null;
    }

    /**
     * @return StructureInterface
     */
    public function getInternalLinkContent()
    {
        return $this->internalLinkContent;
    }

    /**
     * @param StructureInterface $internalLinkContent
     */
    public function setInternalLinkContent($internalLinkContent)
    {
        $this->internalLinkContent = $internalLinkContent;
    }

    /**
     * {@inheritdoc}
     */
    public function hasTag($tag)
    {
        return array_key_exists($tag, $this->tags);
    }

    /**
     * {@inheritdoc}
     */
    public function getTitle($languageCode)
    {
        return $this->metaData->get('title', $languageCode, ucfirst($this->key));
    }

    /**
     * magic getter
     * @param $property string name of property
     * @return mixed
     * @throws NoSuchPropertyException
     */
    public function __get($property)
    {
        if (method_exists($this, 'get' . ucfirst($property))) {
            return $this->{'get' . ucfirst($property)}();
        } else {
            return $this->getProperty($property)->getValue();
        }
    }

    /**
     * magic setter
     * @param $property string name of property
     * @param $value mixed value
     * @return mixed
     * @throws NoSuchPropertyException
     */
    public function __set($property, $value)
    {
        if (isset($this->properties[$property])) {
            return $this->getProperty($property)->setValue($value);
        } else {
            throw new NoSuchPropertyException();
        }
    }

    /**
     * magic isset
     * @param $property
     * @return bool
     */
    public function __isset($property)
    {
        if ($this->findProperty($property) !== null) {
            return true;
        } else {
            return isset($this->$property);
        }
    }

    /**
     * returns an array of property value pairs
     * @param bool $complete True if result should be representation of full node
     * @return array
     */
    public function toArray($complete = true)
    {
        if ($complete) {
            $result = array(
                'id' => $this->uuid,
                'path' => $this->path,
                'nodeType' => $this->nodeType,
                'internal' => $this->internal,
                'nodeState' => $this->getNodeState(),
                'published' => $this->getPublished(),
                'globalState' => $this->getGlobalState(),
                'publishedState' => $this->getPublishedState(),
                'navContexts' => $this->getNavContexts(),
                'enabledShadowLanguages' => $this->getEnabledShadowLanguages(),
                'concreteLanguages' => $this->getConcreteLanguages(),
                'shadowOn' => $this->getIsShadow(),
                'shadowBaseLanguage' => $this->getShadowBaseLanguage(),
                'template' => $this->getKey(),
                'originTemplate' => $this->getOriginTemplate(),
                'hasSub' => $this->hasChildren,
                'creator' => $this->creator,
                'changer' => $this->changer,
                'created' => $this->created,
                'changed' => $this->changed
            );

            if ($this->type !== null) {
                $result['type'] = $this->getType()->toArray();
            }

            if ($this->nodeType === self::NODE_TYPE_INTERNAL_LINK) {
                $result['linked'] = 'internal';
            } elseif ($this->nodeType === self::NODE_TYPE_EXTERNAL_LINK) {
                $result['linked'] = 'external';
            }

            $this->appendProperties($this->getProperties(), $result);

            $result['ext'] = $this->ext;

            return $result;
        } else {
            $result = array(
                'id' => $this->uuid,
                'path' => $this->path,
                'nodeType' => $this->nodeType,
                'internal' => $this->internal,
                'nodeState' => $this->getNodeState(),
                'globalState' => $this->getGlobalState(),
                'publishedState' => $this->getPublishedState(),
                'navContexts' => $this->getNavContexts(),
                'hasSub' => $this->hasChildren,
                'title' => $this->getPropertyValue('title')
            );
            if ($this->type !== null) {
                $result['type'] = $this->getType()->toArray();
            }

            if ($this->nodeType === self::NODE_TYPE_INTERNAL_LINK) {
                $result['linked'] = 'internal';
            } elseif ($this->nodeType === self::NODE_TYPE_EXTERNAL_LINK) {
                $result['linked'] = 'external';
            }

            return $result;
        }
    }

    private function appendProperties($properties, &$array)
    {
        /** @var PropertyInterface $property */
        foreach ($properties as $property) {
            if ($property instanceof SectionPropertyInterface) {
                $this->appendProperties($property->getChildProperties(), $array);
            } else {
                $array[$property->getName()] = $property->getValue();
            }
        }
    }

    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * return available shadow languages on this structure
     * (determined at runtime)
     * @return array
     */
    public function getEnabledShadowLanguages() 
    {
        return $this->enabledShadowLanguages;
    }

    /**
     * set the available enabled shadow languages
     * @param array
     */
    public function setEnabledShadowLanguages($enabledShadowLanguages)
    {
        $this->enabledShadowLanguages = $enabledShadowLanguages;
    }

    public function getConcreteLanguages() 
    {
        return $this->concreteLanguages;
    }
    
    public function setConcreteLanguages($concreteLanguages)
    {
        $this->concreteLanguages = $concreteLanguages;
    }
}
