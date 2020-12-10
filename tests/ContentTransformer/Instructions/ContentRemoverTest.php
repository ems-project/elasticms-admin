<?php
namespace App\Tests\ContentTransformer\Instructions;

use EMS\CoreBundle\ContentTransformer\ContentTransformContext;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\ContentTransformer\Instructions\ContentRemover;

class ContentRemoverTest extends WebTestCase
{
    private function assertEqualsInputOutPut($input, $output)
    {
        $contentTransformContext = ContentTransformContext::fromDataFieldType('testFieldType', $input);
        $contentRemover = new ContentRemover();
        $inputChanged = $contentRemover->transform($contentTransformContext);

        $this->assertEquals($output, $inputChanged);
    }

    public function testInlineNestedInAlertBlock()
    {
        $input = <<<HTML
<div class="message alert">
    <p>Lorem ipsum dolor sit amet, <span class="removable-style-deletedWord">consectetur </span> adipiscing elit.</p>
</div>
HTML;
        $output = <<<HTML
<div class="message alert">
    <p>Lorem ipsum dolor sit amet,  adipiscing elit.</p>
</div>
HTML;

        $this->assertEqualsInputOutPut($input, $output);
    }

    public function testGlobalCleanupScenario()
    {
        $input = <<<HTML
<p>Les employeurs qui occupent ou ont occupé du personnel assujetti à la sécurité sociale ont un nombre d'obligations. Les tiers (fonds de sécurité d'existence, caisses de vacances, etc.) qui versent aux travailleurs des sommes passibles du calcul des cotisations de sécurité sociale sont assimilés aux employeurs.</p>

        <p>Les employeurs qui ne sont pas affiliés à un secrétariat social agréé, reçoivent chaque mois un courrier de l'ONSS reprenant le calcul complet de la provision ainsi qu'une communication structurée spéciale que l'employeur peut seulement utiliser pour le paiement de la provision. Si ce courrier suscite des questions, l'employeur peut prendre contact avec son teneur de compte à la Direction Perception de l'ONSS. Les cotisations visées sont non seulement les cotisations de sécurité sociale au sens strict, mais également toutes les autres cotisations dont la perception a été confiée par la loi à l'O.N.S.S. (cotisations de sécurité d'existence, cotisations destinées au Fonds de fermeture d'entreprises, <span class=" removable-style-deletedWord">retenue</span> <span class=" removable-style-newWord">repris </span>sur le double pécule de vacances, etc.). Néanmoins, les cotisations qui ne sont dues à l'O.N.S.S. qu'une fois l'an ne doivent pas être prises en considération. Il s'agit plus particulièrement du montant de l'avis de débit relatif aux vacances annuelles des travailleurs manuels et du montant de la cotisation de compensation éventuellement due par l'employeur dans le cadre de la redistribution des charges sociales.</p>

        <p>Date de paiement : </p>

        <table border="1" cellpadding="1" cellspacing="1" class="table table-bordered" >
            <tbody>
                <tr>
                    <td >ONEM</td>
                    <td >ONSS</td>
                </tr>
                <tr>
                    <div class=" removable-style-newContent">
                        <td >1er trimestre</td>
                    </div>
                    <td >25 janvier 2009</td>
                </tr>
                <tr>
                    <td >2ème trimestre </td>
                    <td >2 mai 2010</td>
                </tr>
                <tr>
                    <td >3ème trimestre </td>
                    <td >25 juillet <span class=" removable-style-deletedWord">2010</span><span class=" removable-style-newWord">2011</span></td>
                </tr>
            </tbody>
        </table>

        <p>L' employeur qui estime que, respectivement, 35, 30, 25 ou 15 % du montant des cotisations dues pour le trimestre correspondant de l'année précédente seront supérieurs à respectivement 35, 30, 25 ou 15 % du montant des cotisations probables du trimestre en cours, peut réduire <a href="http://www.google.com">le montant</a> de ses provisions à respectivement 35, 30, 25 ou 15 % de ce dernier montant.</p>

        <div class=" removable-style-newContent">
            <p>En outre, pour déterminer si un employeur peut bénéficier du règlement du 22 février 1974 du Comité de Gestion de l'O.N.S.S., il sera tenu compte du respect par celui-ci de l'obligation de payer des provisions. Ce règlement fixe les conditions dans lesquelles un employeur peut obtenir pour un trimestre déterminé, sans application de sanctions, un délai supplémentaire de deux mois pour le paiement de ses cotisations.</p>
        </div>

        <p>Cette réduction de 50 % peut être portée à 100 % par l'O.N.S.S. lorsque l'employeur, à l'appui de sa justification, apporte la preuve qu'au moment de l'exigibilité de la dette, il possédait une créance certaine et exigible à l'égard de l'Etat, d'une province ou d'un établissement public provincial, d'une commune, d'une association de communes ou d'un établissement public communal ou intercommunal, ou d'un organisme d'intérêt public visé à l'article 1er de la loi du 16 mars 1954 relative au contrôle de certains organismes d'intérêt public ou d'une société <span class=" removable-style-newWord">visée</span><a href="http://www.google.be"> à l'article 24</a>. </p>

        <p>Tous les ans, il est également procédé à une redistribution des charges sociales. Cette redistribution consiste en une réduction des cotisations au profit de certains employeurs, qui est compensée par une cotisation supplémentaire à charge d'autres employeurs. Chaque année, dans le courant du deuxième trimestre, l'O.N.S.S. communique aux employeurs le montant du solde créditeur ou débiteur de la redistribution. Le solde créditeur est à valoir sur le montant des cotisations dues par l'employeur pour le deuxième trimestre de l'année en cours. Quant au solde débiteur, il est dû au 30 juin et doit être payé à <a href="http://www.rsz.fgov.be/fr">l'O.N.S.S.</a> au plus tard le 31 juillet.</p>

        <p>Les employeurs qui ne sont pas affiliés à un secrétariat social agréé, reçoivent chaque mois un courrier de l'ONSS reprenant le calcul complet de la provision ainsi qu'une communication structurée spéciale que l'employeur peut seulement utiliser pour le paiement de la provision. Si ce courrier suscite des questions, l'employeur peut prendre contact avec son teneur de compte à la Direction Perception de l'ONSS. <span class=" removable-style-newWord">H5 </span>erere</p>

        <p>Les cotisations visées sont non seulement les cotisations de sécurité sociale au sens strict, mais également toutes les autres cotisations dont la perception a été confiée par la loi à l'O.N.S.S. (cotisations de sécurité d'existence, cotisations destinées au Fonds de fermeture d'entreprises, retenue sur le double pécule de vacances, etc.). Néanmoins, les cotisations qui ne sont dues à l'O.N.S.S. qu'une fois l'an ne doivent pas être prises en considération. Il s'agit plus particulièrement du montant de l'avis de débit relatif aux vacances annuelles des travailleurs manuels et du montant de la cotisation de compensation éventuellement due par l'employeur dans le cadre de la redistribution <span class=" removable-style-deletedWord">des charges sociales</span></p>

        <p>année, <span class=" removable-style-newWord">dans le <strong>courant </strong></span>du deuxième trimestre, l'O.N.S.S. communique aux employeurs le <span class=" removable-style-newWord">montant du <strong>solde créditeur</strong></span> ou débiteur de la redistribution. Le solde créditeur est à valoir sur le montant des cotisations dues par l'employeur pour le deuxième trimestre de l'année en cours. Quant au solde débiteur, il est dû au 30 juin et doit être payé à <a href="http://www.rsz.fgov.be/fr">l'O.N.S.S.</a> au plus tard le 31 juillet.</p>

        <div class="readMoreContent">
            <p><strong style="background-image: url('icons/icon_info_notes_minus.gif');">titre aussi</strong></p>

            <p><span class=" removable-style-newWord">boziefhnpaziohfàzei hgze eroi àeg piozejgpzeojg pzeojgpioze ujpzeoj gzeopj zeop gjzeopgjzeop jze) j </span> </p>

            <p><span class=" removable-style-deletedWord">Sous la forme d'un avis de débit, l'O.N.S.S. envoie annuellement à l'employeur un formulaire reprenant le calcul de cette cotisation sur base des déclarations trimestrielles faites par l'employeur au cours de l'année précédente. Cet avis de débit lui parvient dans le courant du mois de mars; le montant réclamé est dû le 31 mars et doit être payé à l'O.N.S.S. au plus tard le 30 avril.</span></p>
        </div>

        <div class=" removable-style-newContent">
        <table border="1" cellpadding="1" cellspacing="1" >
            <tbody>
                <tr>
                    <td> </td>
                    <td>INAMI </td>
                </tr>
                <tr>
                    <td>Le montant total des cotisations pour l'avant-dernier trimestre(t-2) ne dépassait pas 4.000 EUR : l'employeur n'est pas tenu au paiement de provisions pour ce trimestre. Les cotisations peuvent être payées à l'O.N.S.S. en un seul versement.</td>
                    <div class=" removable-style-newContent">
                        <td>Pour les 1er, 2° et 3° trimestres: le montant des 1ère et 2° provisions mensuelles s'élève à 30 % des cotisations dues pour le trimestre correspondant de l'année précédente. Il doit être payé au plus tard le 5° jour des 2° et 3° mois du trimestre courant.</td>
                    </div>
                </tr>
                <tr>
                    <td> </td>
                    <td> </td>
                </tr>
            </tbody>
        </table>
        </div>

        <p>La plupart des employeurs sont redevables de provisions à l'O.N.S.S. Ici, l'employeur peut consulter le mode de calcul qui lui permettra de vérifier s'il est ou non redevable de ces provisions.</p>

        <ul>
            <li>Les employeurs qui ne sont pas affiliés à un secrétariat social agréé, reçoivent chaque mois un courrier de l'ONSS reprenant le calcul complet de la provision ainsi qu'une communication structurée spéciale que l'employeur peut seulement utiliser pour le paiement de la provision. Si ce courrier suscite des questions, l'employeur peut prendre contact avec son teneur de compte à la Direction Perception de l'ONSS</li>
            <li>La différence entre le montant total des provisions mensuelles et le montant total à payer, tel qu'il a été calculé dans la déclaration trimestrielle, doit parvenir à l'O.N.S.S. au plus tard le dernier jour du mois qui suit le trimestre.</li>
            <li>Le montant total des cotisations pour l'avant-dernier trimestre(t-2) ne dépassait pas 4.000 EUR : l'employeur n'est pas tenu au paiement de provisions pour ce trimestre. Les cotisations peuvent être payées à l'O.N.S.S. en un seul versement. </li>
            <li><span class=" removable-style-deletedWord">Pour les 1er, 2° et 3° trimestres: le montant des 1ère et 2° provisions mensuelles s'élève à 30 % des cotisations dues pour le trimestre correspondant de l'année précédente. Il doit être payé au plus tard le 5° jour des 2° et 3° mois du trimestre courant.</span></li>
            <li><span class=" removable-style-deletedWord">Pour le 4° trimestre: les montants provisionnels s'élèvent à 30, 35 et 15 % des cotisations du trimestre correspondant de l'année précédente, à payer au plus tard le 5 novembre, 5 décembre et 5 janvier.</span></li>
            <li><span class=" removable-style-deletedWord">L' employeur qui estime que, respectivement, 35, 30, 25 ou 15 % du montant des cotisations dues pour le trimestre correspondant de l'année précédente seront supérieurs à respectivement 35, 30, 25 ou 15 % du montant des cotisations probables du trimestre en cours, peut réduire le montant de ses provisions à respectivement 35, 30, 25 ou 15 % de ce dernier montant.</span></li>
            <li>En outre, pour déterminer si un employeur peut bénéficier du règlement du 22 février 1974 du Comité de Gestion de l'O.N.S.S., il sera tenu compte du respect par celui-ci de l'obligation de payer des provisions. Ce règlement fixe les conditions dans lesquelles un employeur peut obtenir pour un trimestre déterminé, sans application de sanctions, un délai supplémentaire de deux mois pour le paiement de ses cotisations.</li>
            <ul>
                <li>A la condition expresse d'avoir au préalable payé toutes ses cotisations échues, l'employeur qui prouve que le non-paiement des provisions dans les délais légaux est dû à des circonstances exceptionnelles, peut obtenir une ré duction maximum de 50 % des sanctions.</li>
                <li><span class=" removable-style-deletedWord">Ce défaut de paiement est intégré dans la notion de "</span></li>
                <li><span class=" removable-style-deletedWord">Une partie des cotisations patronales destinées au financement du pécule de vacances des travailleurs manuels n'est due qu'une fois par an. Il s'agit de la quote-part de 10,27 % calculée sur les rémunérations brutes des travailleurs manuels et des apprentis manuels qui relèvent du régime des vacances annuelles des travailleurs salariés</span></li>
                <li>Sous la forme d'un avis de débit, l'O.N.S.S. envoie annuellement à l'employeur un formulaire reprenant le calcul de cette cotisation sur base des déclarations trimestrielles faites par l'employeur au cours de l'année précédente. Cet avis de débit lui parvient dans le courant du mois de mars; le montant réclamé est dû le 31 mars et doit être payé à l'O.N.S.S. au plus tard le 30 avril.</li>
            </ul>
            <li>Le solde créditeur est à valoir sur le montant des cotisations dues par l'employeur pour le deuxième trimestre de l'année en cours. Quant au solde débiteur, il est dû au 30 juin et doit être payé à l'O.N.S.S. au plus tard le 31 juillet.</li>
            <li>Une période de transition est prévue jusque décembre 2010. Pendant celle-ci, les paiements à l'O.N.S.S. pourront être effectués par versement ou virement au C.C.P. n° 679-0261811-08 de l'O.N.S.S. La date du paiement est celle de l'inscription au compte de l'O.N.S.S.</li>
            <li>Lors de chaque paiement, l'O.N.S.S. doit pouvoir identifier, de façon précise, le compte de l'employeur à créditer. A cet effet, l'employeur communiquera son nom ou sa raison sociale en entier ainsi que son numéro d'entreprise (numéro BCE) complet correct ou son numéro d'identification à l'O.N.S.S. lors de chaque paiement. </li>
        </ul>

        <div class="readMoreContent">
            <p><strong style="background-image: url('icons/icon_info_notes_minus.gif');">Readmore </strong></p>  
            <p>teststststststtestse</p>
        </div>

        <div class="message">
            <p>Les employeurs qui ne sont pas affiliés à un secrétariat social agréé, reçoivent chaque mois un courrier de l'ONSS reprenant le calcul complet de la provision ainsi qu'une communication structurée spéciale que l'employeur peut seulement utiliser pour le paiement de la provision. Si ce courrier suscite des questions, l'employeur peut prendre contact avec son teneur de compte à la Direction Perception de l'ONSS. </p>
        </div>

        <div class="message alert">
            <p>Le montant total des cotisations pour l'avant-dernier trimestre(t-2) ne dépassait pas 4.000 EUR : l'employeur n'est pas tenu au paiement de provisions pour ce trimestre. Les cotisations peuvent être payées à l'O.N.S.S. en un seul versement. </p>
        </div>

        <p>Le montant total des cotisations pour t-2 est plus grand que 4.000 EUR et l'employeur était redevable de cotisations pour t-4 (le trimestre correspondant de l'année calendrier précédente).</p>

        <ul>
            <li><span class="removable-style-newWord">Les cotisations visées sont non seulement les cotisations de sécurité sociale au sens strict, mais également toutes les autres cotisations dont la perception a été confiée par la loi à l'O.N.S.S. (cotisations de sécurité d'existence, cotisations destinées au Fonds de fermeture d'entreprises, retenue sur le double pécule de vacances, etc.).</span></li>
            <li>Néanmoins, les cotisations qui ne sont dues à l'O.N.S.S. qu'une fois l'an ne doivent pas être prises en considération. Il s'agit plus particulièrement du montant de l'avis de débit relatif aux vacances annuelles des travailleurs manuels et du montant de la cotisation de compensation éventuellement due par l'employeur dans le cadre de la redistribution des charges sociales.</li>
            <li>La différence entre le montant total des provisions mensuelles et le montant total à payer, tel qu'il a été calculé dans la déclaration trimestrielle, doit parvenir à l'O.N.S.S. au plus tard le dernier jour du mois qui suit le trimestre.</li>
        </ul>

        <p><span class="hidden">Le montant</span> de la 3° provision mensuelle s'élève à 25 % des cotisations dues pour le trimestre correspondant de la précédente année. Il doit être payé le 5° jour du mois qui suit le trimestre courant.</p>

        <div class="removable-style-newContent">
            <ul>
                <li>Pour le 4° trimestre: les montants provisionnels s'élèvent à 30, 35 et 15 % des cotisations du trimestre correspondant de l'année précédente, à payer au plus tard le 5 novembre, 5 décembre et 5 janvier.</li>
                <li>Dans le cas de l'employeur qui n'était redevable d'aucune cotisation pour t-4, l'employeur est redevable des provisions forfaitaires (possibilité 2).</li>
                <li>Les employeurs qui appartiennent à la Commission paritaire pour la construction, qui doivent payer des provisions procentuelles et connaissent une augmentation d'au moins 3 ouvriers.</li>
                <li>Les employeurs qui appartiennent à la Commission paritaire pour la construction, qui doivent payer des provisions procentuelles et connaissent une augmentation d'au moins 3 ouvriers.</li>
                <li>Pour le 4° trimestre: les montants provisionnels s'élèvent à 30, 35 et 15 % des cotisations du trimestre correspondant de l'année précédente, à payer au plus tard le 5 novembre, 5 décembre et 5 janvier.</li>
            </ul>
        </div>

        <div class="removable-style-newContent">
            <p>En outre, pour déterminer si un employeur peut bénéficier du règlement du 22 février 1974 du Comité de Gestion de l'O.N.S.S., il sera tenu compte du respect par celui-ci de l'obligation de payer des provisions. Ce règlement fixe les conditions dans lesquelles un employeur peut obtenir pour un trimestre déterminé, sans application de sanctions, un délai supplémentaire de deux mois pour le paiement de ses cotisations.</p>
        </div>

        <div class="removable-style-newContent">
            <div class="message alert">
                <p>Les employeurs redevables pour un certain trimestre de provisions forfaitaire (uniquement le régime général-450 EUR) et/ou procentuelle qui ne s'acquittent pas de celles-ci ou s'en acquittent d'une manière insuffisante, sont redevables à l'O.N.S.S. d'une indemnité forfaitaire qui est fonction de la tranche de cotisations déclarées au trimestre concerné. Cette sanction est appliquée comme suit:</p>
            </div>
        </div>

        <div class=" removable-style-newContent">
            <p>Cette réduction de 50 % peut être portée à 100 % par l'O.N.S.S. lorsque l'employeur, à l'appui de sa justification, apporte la preuve qu'au moment de l'exigibilité de la dette, il possédait une créance certaine et exigible à l'égard de l'Etat, d'une province ou d'un établissement public provincial, d'une commune, d'une association de communes ou d'un établissement public communal ou intercommunal, ou d'un organisme d'intérêt public visé à l'article 1er de la loi du 16 mars 1954 relative au contrôle de certains organismes d'intérêt public ou d'une société visée à l'article 24 de la même loi ou lorsque le Comité de gestion admet par décision motivée prise à l'unanimité que des raisons impérieuses d'équité ou d'intérêt économique national ou régional justifient, à titre exceptionnel, pareille réduction.</p>

            <div class="message alert">
                <p>Sous la forme d'un avis de débit, l'O.N.S.S. envoie annuellement à l'employeur un formulaire reprenant le calcul de cette cotisation sur base des déclarations trimestrielles faites par l'employeur au cours de l'année précédente. Cet avis de débit lui parvient dans le courant du mois de mars; le montant réclamé est dû le 31 mars et doit être payé à l'O.N.S.S. au plus tard le 30 avril. </p>
            </div>
        </div>

        <table border="1" class="table table-bordered" >
            <tbody>
                <tr>
                    <td >ONEM</td>
                    <td >ONSS</td>
                </tr>
                <tr>
                    <td >En outre, pour déterminer si un employeur peut bénéficier du règlement du 22 février 1974 du Comité de Gestion de l'O.N.S.S., il sera tenu compte du respect par celui-ci de l'obligation de payer des provisions. Ce règlement fixe les conditions dans lesquelles un employeur peut obtenir pour un trimestre déterminé, sans application de sanctions, un délai supplémentaire de deux mois pour le paiement de ses cotisations.</td>
                    <td >Les employeurs redevables pour un certain trimestre de provisions forfaitaire (uniquement le régime général-450 EUR) et/ou procentuelle qui ne s'acquittent pas de celles-ci ou s'en acquittent d'une manière insuffisante, sont redevables à l'O.N.S.S. d'une indemnité forfaitaire qui est fonction de la tranche de cotisations déclarées au trimestre concerné. Cette sanction est appliquée comme suit:</td>
                </tr>
                <tr>
                    <td >A la condition expresse d'avoir au préalable payé toutes ses cotisations échues, l'employeur qui prouve que le non-paiement des provisions <span class=" removable-style-deletedWord">dans les délais</span> légaux est dû à des circonstances exceptionnelles, peut obtenir une ré duction maximum de <span class=" removable-style-deletedWord">50 %</span> <span class=" removable-style-newWord">60%</span> des sanctions.</td>
                    <td >
                        <div class=" removable-style-deletedContent">
                            <p>Ce défaut de paiement est intégré dans la notion de "dette sociale" qui détermine, dans le cadre de l'article 30bis de la loi du 27 juin 1969, l'obligation d'effectuer une retenue de 35 % sur les factures établies pour des travaux relevant du champ d'application de cet article.</p>
                        </div>
                        <div class=" removable-style-newContent">
                            <p>Une partie des cotisations patronales destinées au financement du pécule de vacances des travailleurs manuels n'est due qu'une fois par an. Il s'agit de la quote-part de 10,27 % calculée sur les rémunérations brutes des travailleurs manuels et des apprentis manuels qui relèvent du régime des vacances annuelles des travailleurs salariés. </p>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <p>Sous la forme d'un avis de débit, l'O.N.S.S. envoie annuellement à l'employeur un formulaire reprenant le calcul de cette cotisation sur base des déclarations trimestrielles faites par l'employeur au cours de l'année précédente. Cet avis de débit lui parvient dans le courant du mois de mars; le montant réclamé est dû le 31 mars et doit être payé à l'O.N.S.S. au plus tard le 30 avril.</p>

        <table border="1" class="table table-bordered" >
            <thead>
                <tr>
                    <th scope="col" ><span class=" removable-style-newWord">ONEM</span></th>
                    <th scope="col" >ONSS</th>
                    <th scope="col" >SPF</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td >LAz</td>
                    <td >zajzea</td>
                    <td >ziae</td>
                </tr>
                <tr>
                    <td >202</td>
                    <td >65563</td>
                    <td >2246</td>
                </tr>
                <tr>
                    <td >2562</td>
                    <td >2698</td>
                    <td >2364</td>
                </tr>
                <tr>
                    <td >Lbas</td>
                    <td >ici </td>
                    <td >ou </td>
                </tr>
            </tbody>
        </table>
HTML;
        $output = <<<HTML
        <p>Les employeurs qui occupent ou ont occupé du personnel assujetti à la sécurité sociale ont un nombre d'obligations. Les tiers (fonds de sécurité d'existence, caisses de vacances, etc.) qui versent aux travailleurs des sommes passibles du calcul des cotisations de sécurité sociale sont assimilés aux employeurs.</p>

        <p>Les employeurs qui ne sont pas affiliés à un secrétariat social agréé, reçoivent chaque mois un courrier de l'ONSS reprenant le calcul complet de la provision ainsi qu'une communication structurée spéciale que l'employeur peut seulement utiliser pour le paiement de la provision. Si ce courrier suscite des questions, l'employeur peut prendre contact avec son teneur de compte à la Direction Perception de l'ONSS. Les cotisations visées sont non seulement les cotisations de sécurité sociale au sens strict, mais également toutes les autres cotisations dont la perception a été confiée par la loi à l'O.N.S.S. (cotisations de sécurité d'existence, cotisations destinées au Fonds de fermeture d'entreprises,  repris sur le double pécule de vacances, etc.). Néanmoins, les cotisations qui ne sont dues à l'O.N.S.S. qu'une fois l'an ne doivent pas être prises en considération. Il s'agit plus particulièrement du montant de l'avis de débit relatif aux vacances annuelles des travailleurs manuels et du montant de la cotisation de compensation éventuellement due par l'employeur dans le cadre de la redistribution des charges sociales.</p>

        <p>Date de paiement : </p>

        <table border="1" cellpadding="1" cellspacing="1" class="table table-bordered" >
            <tbody>
                <tr>
                    <td >ONEM</td>
                    <td >ONSS</td>
                </tr>
                <tr>
                    <td >1er trimestre </td>
                    <td >25 janvier 2009</td>
                </tr>
                <tr>
                    <td >2ème trimestre </td>
                    <td >2 mai 2010</td>
                </tr>
                <tr>
                    <td >3ème trimestre </td>
                    <td >25 juillet 2011</td>
                </tr>
            </tbody>
        </table>

        <p>L' employeur qui estime que, respectivement, 35, 30, 25 ou 15 % du montant des cotisations dues pour le trimestre correspondant de l'année précédente seront supérieurs à respectivement 35, 30, 25 ou 15 % du montant des cotisations probables du trimestre en cours, peut réduire <a href="http://www.google.com">le montant</a> de ses provisions à respectivement 35, 30, 25 ou 15 % de ce dernier montant.</p>
  
        <p>En outre, pour déterminer si un employeur peut bénéficier du règlement du 22 février 1974 du Comité de Gestion de l'O.N.S.S., il sera tenu compte du respect par celui-ci de l'obligation de payer des provisions. Ce règlement fixe les conditions dans lesquelles un employeur peut obtenir pour un trimestre déterminé, sans application de sanctions, un délai supplémentaire de deux mois pour le paiement de ses cotisations.</p>

        <p>Cette réduction de 50 % peut être portée à 100 % par l'O.N.S.S. lorsque l'employeur, à l'appui de sa justification, apporte la preuve qu'au moment de l'exigibilité de la dette, il possédait une créance certaine et exigible à l'égard de l'Etat, d'une province ou d'un établissement public provincial, d'une commune, d'une association de communes ou d'un établissement public communal ou intercommunal, ou d'un organisme d'intérêt public visé à l'article 1er de la loi du 16 mars 1954 relative au contrôle de certains organismes d'intérêt public ou d'une société visée<a href="http://www.google.be"> à l'article 24</a>. </p>

        <p>Tous les ans, il est également procédé à une redistribution des charges sociales. Cette redistribution consiste en une réduction des cotisations au profit de certains employeurs, qui est compensée par une cotisation supplémentaire à charge d'autres employeurs. Chaque année, dans le courant du deuxième trimestre, l'O.N.S.S. communique aux employeurs le montant du solde créditeur ou débiteur de la redistribution. Le solde créditeur est à valoir sur le montant des cotisations dues par l'employeur pour le deuxième trimestre de l'année en cours. Quant au solde débiteur, il est dû au 30 juin et doit être payé à <a href="http://www.rsz.fgov.be/fr">l'O.N.S.S.</a> au plus tard le 31 juillet.</p>

        <p>Les employeurs qui ne sont pas affiliés à un secrétariat social agréé, reçoivent chaque mois un courrier de l'ONSS reprenant le calcul complet de la provision ainsi qu'une communication structurée spéciale que l'employeur peut seulement utiliser pour le paiement de la provision. Si ce courrier suscite des questions, l'employeur peut prendre contact avec son teneur de compte à la Direction Perception de l'ONSS. H5 erere</p>

        <p>Les cotisations visées sont non seulement les cotisations de sécurité sociale au sens strict, mais également toutes les autres cotisations dont la perception a été confiée par la loi à l'O.N.S.S. (cotisations de sécurité d'existence, cotisations destinées au Fonds de fermeture d'entreprises, retenue sur le double pécule de vacances, etc.). Néanmoins, les cotisations qui ne sont dues à l'O.N.S.S. qu'une fois l'an ne doivent pas être prises en considération. Il s'agit plus particulièrement du montant de l'avis de débit relatif aux vacances annuelles des travailleurs manuels et du montant de la cotisation de compensation éventuellement due par l'employeur dans le cadre de la redistribution des charges sociales</p>

        <p>année, dans le <strong>courant </strong>du deuxième trimestre, l'O.N.S.S. communique aux employeurs le montant du <strong>solde créditeur</strong> ou débiteur de la redistribution. Le solde créditeur est à valoir sur le montant des cotisations dues par l'employeur pour le deuxième trimestre de l'année en cours. Quant au solde débiteur, il est dû au 30 juin et doit être payé à <a href="http://www.rsz.fgov.be/fr">l'O.N.S.S.</a> au plus tard le 31 juillet.</p>

        <div class="readMoreContent">
            <p><strong style="background-image: url('icons/icon_info_notes_minus.gif');">titre aussi</strong></p>

            <p>boziefhnpaziohfàzei hgze eroi àeg piozejgpzeojg pzeojgpioze ujpzeoj gzeopj zeop gjzeopgjzeop jze) j  </p>

            <p></p>
        </div>

        <table border="1" cellpadding="1" cellspacing="1" >
            <tbody>
                <tr>
                    <td> </td>
                    <td>INAMI </td>
                </tr>
                <tr>
                    <td>Le montant total des cotisations pour l'avant-dernier trimestre(t-2) ne dépassait pas 4.000 EUR : l'employeur n'est pas tenu au paiement de provisions pour ce trimestre. Les cotisations peuvent être payées à l'O.N.S.S. en un seul versement.</td>          
                    <td>Pour les 1er, 2° et 3° trimestres: le montant des 1ère et 2° provisions mensuelles s'élève à 30 % des cotisations dues pour le trimestre correspondant de l'année précédente. Il doit être payé au plus tard le 5° jour des 2° et 3° mois du trimestre courant.</td>
                </tr>
                <tr>
                    <td> </td>
                    <td> </td>
                </tr>
            </tbody>
        </table>

        <p>La plupart des employeurs sont redevables de provisions à l'O.N.S.S. Ici, l'employeur peut consulter le mode de calcul qui lui permettra de vérifier s'il est ou non redevable de ces provisions.</p>

        <ul>
            <li>Les employeurs qui ne sont pas affiliés à un secrétariat social agréé, reçoivent chaque mois un courrier de l'ONSS reprenant le calcul complet de la provision ainsi qu'une communication structurée spéciale que l'employeur peut seulement utiliser pour le paiement de la provision. Si ce courrier suscite des questions, l'employeur peut prendre contact avec son teneur de compte à la Direction Perception de l'ONSS</li>
            <li>La différence entre le montant total des provisions mensuelles et le montant total à payer, tel qu'il a été calculé dans la déclaration trimestrielle, doit parvenir à l'O.N.S.S. au plus tard le dernier jour du mois qui suit le trimestre.</li>
            <li>Le montant total des cotisations pour l'avant-dernier trimestre(t-2) ne dépassait pas 4.000 EUR : l'employeur n'est pas tenu au paiement de provisions pour ce trimestre. Les cotisations peuvent être payées à l'O.N.S.S. en un seul versement. </li>
            <li>Pour les 1er, 2° et 3° trimestres: le montant des 1ère et 2° provisions mensuelles s'élève à 30 % des cotisations dues pour le trimestre correspondant de l'année précédente. Il doit être payé au plus tard le 5° jour des 2° et 3° mois du trimestre courant.</li>
            <li>Pour le 4° trimestre: les montants provisionnels s'élèvent à 30, 35 et 15 % des cotisations du trimestre correspondant de l'année précédente, à payer au plus tard le 5 novembre, 5 décembre et 5 janvier.</li>
            <li>L' employeur qui estime que, respectivement, 35, 30, 25 ou 15 % du montant des cotisations dues pour le trimestre correspondant de l'année précédente seront supérieurs à respectivement 35, 30, 25 ou 15 % du montant des cotisations probables du trimestre en cours, peut réduire le montant de ses provisions à respectivement 35, 30, 25 ou 15 % de ce dernier montant.</li>
            <li>En outre, pour déterminer si un employeur peut bénéficier du règlement du 22 février 1974 du Comité de Gestion de l'O.N.S.S., il sera tenu compte du respect par celui-ci de l'obligation de payer des provisions. Ce règlement fixe les conditions dans lesquelles un employeur peut obtenir pour un trimestre déterminé, sans application de sanctions, un délai supplémentaire de deux mois pour le paiement de ses cotisations.</li>
            <ul>
                <li>A la condition expresse d'avoir au préalable payé toutes ses cotisations échues, l'employeur qui prouve que le non-paiement des provisions dans les délais légaux est dû à des circonstances exceptionnelles, peut obtenir une ré duction maximum de 50 % des sanctions.</li>
                <li>Ce défaut de paiement est intégré dans la notion de "</li>
                <li>Une partie des cotisations patronales destinées au financement du pécule de vacances des travailleurs manuels n'est due qu'une fois par an. Il s'agit de la quote-part de 10,27 % calculée sur les rémunérations brutes des travailleurs manuels et des apprentis manuels qui relèvent du régime des vacances annuelles des travailleurs salariés</li>
                <li>Sous la forme d'un avis de débit, l'O.N.S.S. envoie annuellement à l'employeur un formulaire reprenant le calcul de cette cotisation sur base des déclarations trimestrielles faites par l'employeur au cours de l'année précédente. Cet avis de débit lui parvient dans le courant du mois de mars; le montant réclamé est dû le 31 mars et doit être payé à l'O.N.S.S. au plus tard le 30 avril.</li>
            </ul>
            <li>Le solde créditeur est à valoir sur le montant des cotisations dues par l'employeur pour le deuxième trimestre de l'année en cours. Quant au solde débiteur, il est dû au 30 juin et doit être payé à l'O.N.S.S. au plus tard le 31 juillet.</li>
            <li>Une période de transition est prévue jusque décembre 2010. Pendant celle-ci, les paiements à l'O.N.S.S. pourront être effectués par versement ou virement au C.C.P. n° 679-0261811-08 de l'O.N.S.S. La date du paiement est celle de l'inscription au compte de l'O.N.S.S.</li>
            <li>Lors de chaque paiement, l'O.N.S.S. doit pouvoir identifier, de façon précise, le compte de l'employeur à créditer. A cet effet, l'employeur communiquera son nom ou sa raison sociale en entier ainsi que son numéro d'entreprise (numéro BCE) complet correct ou son numéro d'identification à l'O.N.S.S. lors de chaque paiement. </li>
        </ul>

        <div class="readMoreContent">
            <p><strong style="background-image: url('icons/icon_info_notes_minus.gif');">Readmore </strong></p>  
            <p>teststststststtestse</p>
        </div>

        <div class="message">
            <p>Les employeurs qui ne sont pas affiliés à un secrétariat social agréé, reçoivent chaque mois un courrier de l'ONSS reprenant le calcul complet de la provision ainsi qu'une communication structurée spéciale que l'employeur peut seulement utiliser pour le paiement de la provision. Si ce courrier suscite des questions, l'employeur peut prendre contact avec son teneur de compte à la Direction Perception de l'ONSS. </p>
        </div>

        <div class="message alert">
            <p>Le montant total des cotisations pour l'avant-dernier trimestre(t-2) ne dépassait pas 4.000 EUR : l'employeur n'est pas tenu au paiement de provisions pour ce trimestre. Les cotisations peuvent être payées à l'O.N.S.S. en un seul versement. </p>
        </div>

        <p>Le montant total des cotisations pour t-2 est plus grand que 4.000 EUR et l'employeur était redevable de cotisations pour t-4 (le trimestre correspondant de l'année calendrier précédente).</p>

        <ul>
            <li>Les cotisations visées sont non seulement les cotisations de sécurité sociale au sens strict, mais également toutes les autres cotisations dont la perception a été confiée par la loi à l'O.N.S.S. (cotisations de sécurité d'existence, cotisations destinées au Fonds de fermeture d'entreprises, retenue sur le double pécule de vacances, etc.).</li>
            <li>Néanmoins, les cotisations qui ne sont dues à l'O.N.S.S. qu'une fois l'an ne doivent pas être prises en considération. Il s'agit plus particulièrement du montant de l'avis de débit relatif aux vacances annuelles des travailleurs manuels et du montant de la cotisation de compensation éventuellement due par l'employeur dans le cadre de la redistribution des charges sociales.</li>
            <li>La différence entre le montant total des provisions mensuelles et le montant total à payer, tel qu'il a été calculé dans la déclaration trimestrielle, doit parvenir à l'O.N.S.S. au plus tard le dernier jour du mois qui suit le trimestre.</li>
        </ul>

        <p><span class="hidden">Le montant</span> de la 3° provision mensuelle s'élève à 25 % des cotisations dues pour le trimestre correspondant de la précédente année. Il doit être payé le 5° jour du mois qui suit le trimestre courant.</p>

        <ul>
            <li>Pour le 4° trimestre: les montants provisionnels s'élèvent à 30, 35 et 15 % des cotisations du trimestre correspondant de l'année précédente, à payer au plus tard le 5 novembre, 5 décembre et 5 janvier.</li>
            <li>Dans le cas de l'employeur qui n'était redevable d'aucune cotisation pour t-4, l'employeur est redevable des provisions forfaitaires (possibilité 2).</li>
            <li>Les employeurs qui appartiennent à la Commission paritaire pour la construction, qui doivent payer des provisions procentuelles et connaissent une augmentation d'au moins 3 ouvriers.</li>
            <li>Les employeurs qui appartiennent à la Commission paritaire pour la construction, qui doivent payer des provisions procentuelles et connaissent une augmentation d'au moins 3 ouvriers.</li>
            <li>Pour le 4° trimestre: les montants provisionnels s'élèvent à 30, 35 et 15 % des cotisations du trimestre correspondant de l'année précédente, à payer au plus tard le 5 novembre, 5 décembre et 5 janvier.</li>
        </ul>

        <p>En outre, pour déterminer si un employeur peut bénéficier du règlement du 22 février 1974 du Comité de Gestion de l'O.N.S.S., il sera tenu compte du respect par celui-ci de l'obligation de payer des provisions. Ce règlement fixe les conditions dans lesquelles un employeur peut obtenir pour un trimestre déterminé, sans application de sanctions, un délai supplémentaire de deux mois pour le paiement de ses cotisations.</p>

        <div class="message alert">
            <p>Les employeurs redevables pour un certain trimestre de provisions forfaitaire (uniquement le régime général-450 EUR) et/ou procentuelle qui ne s'acquittent pas de celles-ci ou s'en acquittent d'une manière insuffisante, sont redevables à l'O.N.S.S. d'une indemnité forfaitaire qui est fonction de la tranche de cotisations déclarées au trimestre concerné. Cette sanction est appliquée comme suit:</p>
        </div>

        <p>Cette réduction de 50 % peut être portée à 100 % par l'O.N.S.S. lorsque l'employeur, à l'appui de sa justification, apporte la preuve qu'au moment de l'exigibilité de la dette, il possédait une créance certaine et exigible à l'égard de l'Etat, d'une province ou d'un établissement public provincial, d'une commune, d'une association de communes ou d'un établissement public communal ou intercommunal, ou d'un organisme d'intérêt public visé à l'article 1er de la loi du 16 mars 1954 relative au contrôle de certains organismes d'intérêt public ou d'une société visée à l'article 24 de la même loi ou lorsque le Comité de gestion admet par décision motivée prise à l'unanimité que des raisons impérieuses d'équité ou d'intérêt économique national ou régional justifient, à titre exceptionnel, pareille réduction.</p>

        <div class="message alert">
            <p>Sous la forme d'un avis de débit, l'O.N.S.S. envoie annuellement à l'employeur un formulaire reprenant le calcul de cette cotisation sur base des déclarations trimestrielles faites par l'employeur au cours de l'année précédente. Cet avis de débit lui parvient dans le courant du mois de mars; le montant réclamé est dû le 31 mars et doit être payé à l'O.N.S.S. au plus tard le 30 avril. </p>
        </div>

        <table border="1" class="table table-bordered" >
            <tbody>
                <tr>
                    <td >ONEM</td>
                    <td >ONSS</td>
                </tr>
                <tr>
                    <td >En outre, pour déterminer si un employeur peut bénéficier du règlement du 22 février 1974 du Comité de Gestion de l'O.N.S.S., il sera tenu compte du respect par celui-ci de l'obligation de payer des provisions. Ce règlement fixe les conditions dans lesquelles un employeur peut obtenir pour un trimestre déterminé, sans application de sanctions, un délai supplémentaire de deux mois pour le paiement de ses cotisations.</td>
                    <td >Les employeurs redevables pour un certain trimestre de provisions forfaitaire (uniquement le régime général-450 EUR) et/ou procentuelle qui ne s'acquittent pas de celles-ci ou s'en acquittent d'une manière insuffisante, sont redevables à l'O.N.S.S. d'une indemnité forfaitaire qui est fonction de la tranche de cotisations déclarées au trimestre concerné. Cette sanction est appliquée comme suit:</td>
                </tr>
                <tr>
                    <td >A la condition expresse d'avoir au préalable payé toutes ses cotisations échues, l'employeur qui prouve que le non-paiement des provisions  légaux est dû à des circonstances exceptionnelles, peut obtenir une ré duction maximum de  60% des sanctions.</td>
                    <td >
                        <p>Ce défaut de paiement est intégré dans la notion de "dette sociale" qui détermine, dans le cadre de l'article 30bis de la loi du 27 juin 1969, l'obligation d'effectuer une retenue de 35 % sur les factures établies pour des travaux relevant du champ d'application de cet article.</p>
                        <p>Une partie des cotisations patronales destinées au financement du pécule de vacances des travailleurs manuels n'est due qu'une fois par an. Il s'agit de la quote-part de 10,27 % calculée sur les rémunérations brutes des travailleurs manuels et des apprentis manuels qui relèvent du régime des vacances annuelles des travailleurs salariés. </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <p>Sous la forme d'un avis de débit, l'O.N.S.S. envoie annuellement à l'employeur un formulaire reprenant le calcul de cette cotisation sur base des déclarations trimestrielles faites par l'employeur au cours de l'année précédente. Cet avis de débit lui parvient dans le courant du mois de mars; le montant réclamé est dû le 31 mars et doit être payé à l'O.N.S.S. au plus tard le 30 avril.</p>

        <table border="1" class="table table-bordered" >
            <thead>
                <tr>
                    <th scope="col" >ONEM</th>
                    <th scope="col" >ONSS</th>
                    <th scope="col" >SPF</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td >LAz</td>
                    <td >zajzea</td>
                    <td >ziae</td>
                </tr>
                <tr>
                    <td >202</td>
                    <td >65563</td>
                    <td >2246</td>
                </tr>
                <tr>
                    <td >2562</td>
                    <td >2698</td>
                    <td >2364</td>
                </tr>
                <tr>
                    <td >Lbas</td>
                    <td >ici </td>
                    <td >ou </td>
                </tr>
            </tbody>
        </table>
HTML;

        $this->assertEqualsInputOutPut($input, $output);
    }
}
