<?php


namespace App\Command\Ibpt;


class Linker
{
    public static function determineDossier($string): ?string
    {
        if (\in_array($string, ['Utilisateurs - documents légaux', 'Gebruikers - wettelijke documenten', 'Verbraucher', 'Consumers'])) {
            return 'file_reference:AW1jHTF2-Sp0TD2f1kIG';
        }
        if (\in_array($string, ['Numéros - documents légaux', 'Nummers - wettelijke documenten', 'Nummern', 'Numbers - legal documents'])) {
            return 'file_reference:AW1jHdO_-Sp0TD2f1kIg';
        }
        if (\in_array($string, ['Commission d\'Éthique - documents légaux', 'Ethische Commissie - wettelijke documenten', 'Ethische Commissie - wettelijke documenten', 'Ethics Commission'])) {
            return 'file_reference:AW1jHnUJ-Sp0TD2f1kIw';
        }
        if (\in_array($string, ['Rapports annuels', 'Jaarverslagen', 'Jahresberichten', 'Annual reports'])) {
            return 'file_reference:AW1jHxfM-Sp0TD2f1kJD';
        }
        if (\in_array($string, ['Plans de gestion', 'Beheersplannen', 'Verwaltungspläne', 'Management plans'])) {
            return 'file_reference:AW1jH81a-Sp0TD2f1kJT';
        }
        if (\in_array($string, ['Rapports au Parlement', 'Verslagen aan het Parlement', 'Berichte an das Parlament', 'Reports to the Parliament'])) {
            return 'file_reference:AW1jIEK0-Sp0TD2f1kKF';
        }
        if (\in_array($string, ['Plans stratégiques', 'Strategisch plannen', 'Strategische Pläne', 'Strategic plans'])) {
            return 'file_reference:AW1jIOEp-Sp0TD2f1kKX';
        }
        if (\in_array($string, ['Plans opérationnels', 'Werkplannen', 'Arbeitspläne', 'Operational plans'])) {
            return 'file_reference:AW1jIWKP-Sp0TD2f1kKu';
        }
        if (\in_array($string, ['Révision des offres de référence BRUO / BROBA'])) {
            return 'file_reference:AW1jIcvA-Sp0TD2f1kK7';
        }
        if (\in_array($string, ['Marktanalyse besluit 2006 mbt clustertelefoni'])) {
            return 'file_reference:AW1jIiMa-Sp0TD2f1kLT';
        }
        if (\in_array($string, ['Marktanalyse besluit 2006 mbt retail toegang'])) {
            return 'file_reference:AW1jIn58-Sp0TD2f1kLq';
        }

        return null;
    }
}
