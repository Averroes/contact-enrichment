<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Nicholai Bush <nbush@thedmsgrp.com>
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\ContactEnrichmentBundle\Integration;

use Doctrine\ORM\OptimisticLockException;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\PluginBundle\Exception\ApiErrorException;
use Mautic\PluginBundle\Integration\AbstractIntegration;
use MauticPlugin\ContactEnrichmentBundle\Event\ContactEnricmentEvent;
use MauticPlugin\ContactEnrichmentBundle\ContactEnrichmentEvents;

/**
 * Class AbstractEnhancerIntegration.
 *
 * @method string getAuthorizationType()
 * @method string getName()
 */
abstract class AbstractContactEnrichmentIntegration extends AbstractIntegration
{
    /**
     * @return string
     */
    public function getDisplayName()
    {
        $readableName = preg_replace('/([a-z])([A-Z])/', '$1 $2', $this->getName());

        return sprintf('%s Enrichment', $readableName);
    }

    /** @var \Mautic\PluginBundle\Integration\AbstractIntegration */
    protected $integration;

    /**
     * ContactEnrichmentBundle ads several new features
     *
     * * contacr_enrichment - finds new and/or validates existing contact information
     * * auto_runable       - automatically run contact enrichers when leads are first identified
     * * invoicable         - keep track of how much some contact enrichers cost
     *
     * `push_lead` is enabled by default
     * `contact_enrichment is always enabled
     *
     * @param string[] $supportedFeatures
     *
     * @return $this
     */
    public function setSupportedFeatures($supportedFeatures)
    {
        return $this;
    }

    /**
     * @return array
     */
    public function getSupportedFeatureTooltips()
    {
        $local = [
            'contact_enrichment' => 'contact.enrichment.contact.enrictment',
            'auto_runable'=> 'contact.enricment.auto.run',
            'invoicable' => 'contact.enrichment.invoiceable',
        ];
        return array_merge(parent::getSupportedFeatureTooltips(), $local);
    }
    /**
     * @returns array[]
     */
    abstract protected function getContactEnrichmentLabels();

    /**
     * @param Lead $lead
     */
    abstract protected function doContactEnrichment(Lead $lead);

    /**
     * @param array $leadFieldDef
     */
    protected function addLeadField($leadFieldDef)
    {
    }

    /**
     * @return string
     */
    protected function getLeadFieldObject()
    {
        return class_exists('MauticPlugin\MauticExtendedFieldBundle\MauticExtendedFieldBundle') ?
            'extendedField' :
            'lead';
    }

    /**
     * @param Lead  $lead
     * @param array $config
     *
     * @return bool
     */
    public function pushLead(Lead &$lead, array $config = [])
    {
        $this->logger->debug('Pushing to Enhancer '.$this->getName(), $config);
        $this->config         = $config;
        $this->isPush         = true;

        try {
            $this->doContactEnrichment($lead);
            $this->saveLead($lead);
        } catch (\Exception $exception) {
            $this->logIntegrationError($exception, $lead);
            return true;
        }

        $event = new ContactEnricmentEvent($this, $lead, $this->getCampaign());
        $this->dispatcher->dispatch(ContactEnrichmentEvents::CONTACT_ENRICHMENT_COMPLETED, $event);

        // Always return true to prevent campaign actions from being halted, even if an enhancer fails.
        return true;
    }

    /**
     * @return bool|\Doctrine\Common\Proxy\Proxy|\Mautic\CampaignBundle\Entity\Campaign|null|object
     */
    private function getCampaign()
    {
        if (!$this->campaign) {
            $config = $this->config;
            try {
                if (is_int($config['campaignId'])) {
                    // In the future a core fix may provide the correct campaign id.
                    $this->campaign = $this->em->getReference(
                        'Mautic\CampaignBundle\Enitity\Campaign',
                        $config['campaignId']
                    );
                } else {
                    // Otherwise we must obtain it from the unit of work.
                    /** @var \Doctrine\ORM\UnitOfWork $identityMap */
                    $identityMap = $this->em->getUnitOfWork()->getIdentityMap();
                    if (isset($identityMap['Mautic\CampaignBundle\Entity\LeadEventLog'])) {
                        /** @var \Mautic\LeadBundle\Entity\LeadEventLog $leadEventLog */
                        foreach ($identityMap['Mautic\CampaignBundle\Entity\LeadEventLog'] as $leadEventLog) {
                            $properties = $leadEventLog->getEvent()->getProperties();
                            if (
                                $properties['_token'] === $config['_token']
                                && $properties['campaignId'] === $config['campaignId']
                            ) {
                                $this->campaign = $leadEventLog->getCampaign();
                                break;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
            }
        }

        return $this->campaign;
    }

    /**
     * @param Lead $lead
     */
    public function applyCost($lead)
    {
        $costPerEnhancement = $this->getCostPerEnhancement();
        if ($costPerEnhancement) {
            $attribution = $lead->getFieldValue('attribution');
            // $lead->attribution -= $costPerEnhancement;
            $lead->addUpdatedField(
                'attribution',
                $attribution - $costPerEnhancement,
                $attribution
            );
        }
    }

    /**
     * Return null if there is no cost attributed to the integration.
     */
    public function getCostPerEnhancement()
    {
        return null;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->getIntegrationSettings()->getId();
    }

    /**
     * @param $lead
     */
    public function saveLead($lead)
    {
        $event = new ContactLedgerContextEvent(
            $this->campaign, $this, 'enhanced', $lead
        );
        $this->dispatcher->dispatch(
            'mautic.contactledger.context_create',
            $event
        );
        $this->leadModel->saveEntity($lead);
    }
}
