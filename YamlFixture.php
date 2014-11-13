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

    public static function uncamelize($string, $separator = '_')
    {
        // http://stackoverflow.com/a/19533226
        return ltrim(strtolower(preg_replace('/[A-Z]/', $separator.'$0', $string)), $separator);
    }

    public static function camelize($string, $separator = '_')
    {
        $parts = split($separator, $string);
        foreach ($parts as &$part) {
            $part = ucfirst($part);
        }

        return implode('', $parts);
    }

    public function load(ObjectManager $manager)
    {
        $objects = Yaml::parse(file_get_contents($this->getFilePath()));
        foreach ($objects as $id => $object) {
            $manager->persist($this->handleObject($object, $id));
        }

        $manager->flush();
    }

    /**
     * Handle the logic on one object
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
            $this->handleField($id, $field, $value, $fields, $entity);
        }

        $this->addReference($reference, $entity);

        return $entity;
    }

    /**
     * Compute a prefix based on entity name (without namespace) used to identify references
     * 
     * @return string
     */
    protected function getReferencePrefix()
    {
        $class = new \ReflectionClass($this->getEntity());

        return self::uncamelize($class->getShortName(), '-');
    }

    /**
     * Handle the logic on one field
     * 
     * @param string $field
     * @param mixed $value
     */
    protected function handleField($id, &$field, $value, array $fields, $entity)
    {
        $this->overrideValue($id, &$value, $fields);
        $setter = $this->defineSetter($field, $value);

        if ($field[0] === '@') {
            $this->byReference($entity, $setter, $value);
        } else {
            $entity->$setter($value);
        }
    }

    /**
     * Replace keywords
     * 
     * @param string $id
     * @param mixed $value
     * @param array $fields
     * @return mixed
     */
    protected function overrideValue($id, $value, array $fields)
    {
        if (is_string($value) === false) {
            return $value;
        }

        if ($value === '%self%') {
            return $id;
        } else if ($value[0] === '%' && substr($value, -1) === '%' && isset($fields[substr($value, 1, -1)]) === true) {
            return $fields[substr($value, 1, -1)];
        }

        return $value;
    }

    /**
     * Guess the appropriate setter for the current field
     * 
     * @param type $field
     * @param type $value
     * @return type
     */
    protected function defineSetter($field, $value)
    {
        if ($field[0] === '@') {
            return sprintf('%s%s', self::camelize(substr($field, 1)), (is_array($value) === true) ? 'add' : 'set');
        }

        return sprintf('set%s', self::camelize($field));
    }

    /**
     * Append values by reference (reference to an entity)
     * 
     * @param type $entity
     * @param type $setter
     * @param type $value
     */
    protected function byReference($entity, $setter, $value)
    {
        if (is_array($value)) {
            foreach ($value as $row) {
                $entity->$setter($this->getReference($row));
            }
        } else {
            $entity->$setter($this->getReference($value));
        }
    }
}
