<?php

/*
 *  This file is part of the Creakiwi\Component\YamlFixtures package.
 * 
 * (c) Alexandre ANDRE <alexandre@creakiwi.com>
 * 
 * For the full copyright and license information please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Creakiwi\Component\YamlFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Yaml\Yaml;

/**
 * A simple abstraction class to handle Yaml fixtures into doctrine fixtures interface
 *
 * @author Alexandre ANDRE
 */
abstract class YamlFixture extends AbstractFixture implements OrderedFixtureInterface
{
    /**
     * @return string The file path storing yaml fixtures
     */
    abstract protected function getFilePath();

    /**
     * @return mixed A new entity related to data loaded
     */
    abstract protected function getEntity();

    /**
     * @return string A prefix used to identify references
     */
    abstract protected function getReferencePrefix();

    public function load(ObjectManager $manager)
    {
        $objects = Yaml::parse(file_get_contents($this->getFilePath()));
        foreach ($objects as $id => $object) {
            $manager->persist($this->handleObject($object, $id));
        }

        $manager->flush();
    }

    /**
     * 
     * @param array $fields
     * @param type $id
     * @return mixed Fully initialized entity from fixtures
     */
    protected function handleObject(array $fields, $id)
    {
        $reference  = sprintf('%s-%s', $this->getReferencePrefix(), $id);
        $entity     = $this->getEntity();

        foreach ($fields as $field => &$value) {
            $this->handleField($field, &$value, $fields);
        }

        $this->addReference($reference, $entity);

        return $entity;
    }

    /**
     * @param string $field
     * @param mixed $value
     */
    protected function handleField($field, $value, array $fields)
    {
        //STOP HERE
        $by_reference   = false;
        $value          = $this->overrideValue($fields, $value, $field);

        if ($key[0] === '@') {
            $by_reference   = true;
            $key            = substr($key, 1);
        }

        $key = split('_', $key);
        foreach ($key as &$part)
            $part = ucfirst($part);
        $setter = sprintf('set%s', implode('', $key));

        if ($by_reference === true) {
            if (is_array($value))
                $setter = sprintf('add%s', implode('', $key));

            $this->byReference($entity, $setter, $value);
        }
        else
            $entity->$setter($value);
    }

    protected function overrideValue($object, $value, $key)
    {
        if (is_string($value) === false)
            return $value;

        if ($value === '%self%')
            return $key;
        else if ($value[0] === '%'
            && substr($value, -1) === '%'
            && isset($object[substr($value, 1, -1)]) === true)
            return $object[substr($value, 1, -1)];

        return $value;
    }

    protected function byReference($entity, $setter, $value)
    {
        if (is_array($value)) {
            foreach ($value as $row)
                $entity->$setter($this->getReference($row));
        }
        else
            $entity->$setter($this->getReference($value));
    }
}
