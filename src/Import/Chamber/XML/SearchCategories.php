<?php

namespace App\Import\Chamber\XML;

class SearchCategories
{
    // legal work
    const CAT_FLWB_LAW_PROPOSAL         = 'flwb_law_proposal';
    const CAT_FLWB_LAW_PROJECT          = 'flwb_law_project';
    const CAT_FLWB_RESOLUTION_PROPOSAL  = 'flwb_resolution_proposal';
    //const CAT_FLWB_NATURALIZATION       = 'flwb_naturalization';
    const CAT_FLWB_BUDGET               = 'flwb_budget';
    const CAT_FLWB_TEXT_ADOPTED         = 'flwb_text_adopted';
    const CAT_FLWB_LAW_PUBLICATION      = 'flwb_law_publication';
    const CAT_FLWB_AMENDMENT            = 'flwb_amendment';
    const CAT_FLWB_STATE_ADVISE         = 'flwb_state_advise';
    const CAT_FLWB_COMMISSION_REPORT    = 'flwb_commission_report';
    const CAT_FLWB_PDF                  = 'flwb_pdf';
    const CAT_FLWB_MOTION               = 'flwb_motion';
    const CAT_FLWB_OTHER                = 'flwb_other';
    const CAT_GENESIS_OTHER             = 'genesis_other';

    // control
    const CAT_INQO                      = 'inqo';
    const CAT_INQO_MOTI                 = 'inqo_moti';
    const CAT_QRVA                      = 'qrva';

    // reports
    const CAT_PCRI                      = 'pcri';
    const CAT_PCRA                      = 'pcra';
    const CAT_CCRI                      = 'ccri';
    const CAT_CCRA                      = 'ccra';

    //actors
    const CAT_ACTR_ACTIVE_MEMBER = 'actr_active_member'; //150
    const CAT_ACTR_MEMBER        = 'actr_member';
    const CAT_ACTR_HONORARY      = 'actr_honorary';
    const CAT_ACTR_GOVERNMENT    = 'actr_government';
    const CAT_ACTR_SENATOR       = 'actr_senator';
    const CAT_ACTR_OTHER         = 'actr_other';

    // others
    const CAT_MTNG                      = 'mtng';
    const CAT_COMMISSION                = 'commission';
    const CAT_FLWB_GOVERNMENT_STATEMENT = 'flwb_government_statement';
    const CAT_FLWB_NOMINATION_CANDIDATE = 'flwb_nomination_candidate';

    public static function forFLWB(FLWB $flwb): array
    {
        $categories = [];

        if ($flwb->isNotFirstBornOrCopy()) {
            self::catFlwbLawProposal($flwb, $categories);
            self::catFlwbLawProject($flwb, $categories);
            self::catFlwbResolutionProposal($flwb, $categories);
            self::catFlwbNaturalization($flwb, $categories);
        }

        self::catFlwbBudget($flwb, $categories);
        self::catFlwbTextAdopted($flwb, $categories);
        self::catFlwbLawPublication($flwb, $categories);
        self::catFlwbStateAdvise($flwb, $categories);
        self::catFlwbMotion($flwb, $categories);

        self::catFlwbGovernmentStatement($flwb, $categories);
        self::catFlwbNominationCandidate($flwb, $categories);

        if ($categories === null) {
            $categories[] = self::CAT_FLWB_OTHER;
        }

        return $categories;
    }

    public static function forGENESIS(GENESIS $genesis): array
    {
        return [self::CAT_GENESIS_OTHER];
    }

    private static function catFlwbLawProposal(FLWB $flwb, array &$categories): void
    {
        if ($flwb->isMainDocType(FLWBDoc::DOC_TYPE_LAW_PROPOSAL)) {
            $categories[] = self::CAT_FLWB_LAW_PROPOSAL;
        }
    }

    private static function catFlwbLawProject(FLWB $flwb, array &$categories): void
    {
        if ($flwb->isMainDocType(FLWBDoc::DOC_TYPE_LAW_PROJECT)) {
            $categories[] = self::CAT_FLWB_LAW_PROJECT;
        }
    }

    private static function catFlwbResolutionProposal(FLWB $flwb, array &$categories): void
    {
        if ($flwb->isMainDocType(FLWBDoc::DOC_TYPE_RESOLUTION_PROPOSAL)) {
            $categories[] = self::CAT_FLWB_RESOLUTION_PROPOSAL;
        }
    }

    private static function catFlwbNaturalization(FLWB $flwb, array &$categories): void
    {
        if ($flwb->inMainDocTypes(FLWBDoc::DOC_TYPE_NATURALIZATION)) {
            //See jira http://jira.smals.be/browse/WEBCHAMBER-678
            $categories[] = self::CAT_FLWB_OTHER;
        }
    }

    private static function catFlwbBudget(FLWB $flwb, array &$categories): void
    {
        if ($flwb->inTitle('Budget général des dépenses')
            || $flwb->inTitle('Notes de politique générale')) {
            $categories[] = self::CAT_FLWB_BUDGET;
        }
    }

    private static function catFlwbTextAdopted(FLWB $flwb, array &$categories): void
    {
        if ($flwb->inStatusChamber('fr', 'adopte')) {
            $categories[] = self::CAT_FLWB_TEXT_ADOPTED;
        }
    }

    private static function catFlwbLawPublication(FLWB $flwb, array &$categories): void
    {
        if ($flwb->hasPublicationDate()) {
            $categories[] = self::CAT_FLWB_LAW_PUBLICATION;
        }
    }

    private static function catFlwbStateAdvise(FLWB $flwb, array &$categories): void
    {
        if ($flwb->inDocTypes(FLWBDoc::DOC_TYPE_STATE_ADVISE)) {
            $categories[] = self::CAT_FLWB_STATE_ADVISE;
        }
    }

    private static function catFlwbMotion(FLWB $flwb, array &$categories): void
    {
        if ($flwb->inDocTypes(FLWBDoc::DOC_TYPE_MOTION)) {
            $categories[] = self::CAT_FLWB_MOTION;
        }
    }

    private static function catFlwbGovernmentStatement(FLWB $flwb, array &$categories): void
    {
        if ($flwb->inTitle('Déclaration du Gouvernement fédéral')) {
            $categories[] = self::CAT_FLWB_GOVERNMENT_STATEMENT;
        }
    }

    private static function catFlwbNominationCandidate(FLWB $flwb, array &$categories): void
    {
        if ($flwb->inDocTypes(FLWBDoc::DOC_TYPE_NOMINATION_CANDIDATE)) {
            $categories[] = self::CAT_FLWB_NOMINATION_CANDIDATE;
        }
    }
}