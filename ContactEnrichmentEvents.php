<?php

namespace MauticPlugin\ContactEnrichmentBundle;

/**
 * Class ContactEnrichmentEvents
 *
 * @package MauticPlugin\ContactEnrichmentBundle
 */
final class ContactEnrichmentEvents
{
    /**
     * Fired when an enhancer completes.
     *
     * The event listener receives a
     * MauticPlugin\ContactEnrichmentBundle\Event\MauticEnhancerEvent.
     *
     * @var string
     */
    const CONTACT_ENRICHMENT_COMPLETED = 'plugin.contact_enrichment.event.completed';
}
