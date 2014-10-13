<?php

/*
 *  This file is part of the Creakiwi\YamlFixtures package.
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
 * Description of YamlFixture
 *
 * @author Alexandre ANDRE
 */
abstract class YamlFixture extends AbstractFixture implements OrderedFixtureInterface
{
    abstract protected function getFilePath();
    abstract protected function getEntity();
    abstract protected function getReferencePrefix();

    public function load(ObjectManager $manager)
    {
        $objects = Yaml::parse(file_get_contents($this->getFilePath()));
        foreach ($objects as $object_key => $object) {
            $reference  = sprintf('%s-%s', $this->getReferencePrefix(), $object_key);
            $entity     = $this->getEntity();

            foreach ($object as $key => &$value) {
                $by_reference   = false;
                $value          = $this->overrideValue($object, $value, $object_key);

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

            $manager->persist($entity);
            $this->addReference($reference, $entity);
        }

        $manager->flush();
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
