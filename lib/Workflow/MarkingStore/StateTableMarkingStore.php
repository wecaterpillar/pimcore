<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Workflow\MarkingStore;

use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;
use Pimcore\Model\Element\WorkflowState;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

class StateTableMarkingStore implements MarkingStoreInterface
{
    /**
     * @var string
     */
    private $workflowName;

    public function __construct(string $workflowName)
    {
        $this->workflowName = $workflowName;
    }

    /**
     * {@inheritdoc}
     *
     * @return Marking
     */
    public function getMarking($subject)// : Marking
    {
        $subject = $this->checkIfSubjectIsValid($subject);

        $placeName = '';

        if ($workflowState = WorkflowState::getByPrimary($subject->getId(), Service::getElementType($subject), $this->workflowName)) {
            $placeName = $workflowState->getPlace();
        }

        if (!$placeName) {
            return new Marking();
        }

        $placeName = explode(',', $placeName);
        $places = [];
        foreach ($placeName as $place) {
            $places[$place] = 1;
        }

        return new Marking($places);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function setMarking($subject, Marking $marking, array $context = [])
    {
        $subject = $this->checkIfSubjectIsValid($subject);
        $type = Service::getElementType($subject);

        if (!$workflowState = WorkflowState::getByPrimary($subject->getId(), $type, $this->workflowName)) {
            $workflowState = new WorkflowState();
            $workflowState->setCtype($type);
            $workflowState->setCid($subject->getId());
            $workflowState->setWorkflow($this->workflowName);
        }

        $workflowState->setPlace(implode(',', array_keys($marking->getPlaces())));
        $workflowState->save();
    }

    /**
     * @return string
     */
    public function getProperty()
    {
        return $this->workflowName;
    }

    /**
     * @throws LogicException
     */
    private function checkIfSubjectIsValid(object $subject): ElementInterface
    {
        if (!$subject instanceof ElementInterface) {
            throw new LogicException('state_table marking store works for pimcore elements (documents, assets, data objects) only.');
        }

        return $subject;
    }
}
