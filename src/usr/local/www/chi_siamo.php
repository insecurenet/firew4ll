<?php
/*
 * chi_siamo.php
 *
 * part of Firew4LL (https://www.firew4ll.com)
 * Copyright (c) 2020 InSecureNet, SRL (ISN)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=chi_siamo
##|*NAME=Aiuto: Chi Siamo
##|*DESCR=Pagina di informazione sul progetto
##|*MATCH=chi_siamo.php*
##|-PRIV

require_once("guiconfig.inc");

$pgtitle = array(gettext("Aiuto"), gettext("Chi Siamo"));
$shortcut_section = "chi_siamo";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

echo '<h1 class="entry-title">Chi siamo</h1>    </header><!-- .entry-header -->';
echo '';
echo '<div class="entry-content">';
echo '<p style="text-align: justify;"><span style="color: #000000;">Da un\'idea di Francesco Pellegrino di <a href="https://www.insecurenet.com/">InSecureNet</a> che, con la sua esperienza lavorativa ha identificato nella <em>disinformazione</em> la maggiore vulnerabilità informatica,  e anche grazie all’iniziativa del <a href="http://pingiovani.regione.puglia.it/">Progetto PIN</a> promosso dalla <a style="color: #000000;" href="https://www.regione.puglia.it/">Regione Puglia</a>, nasce <a href="https://www.firew4ll.com/">Firew4LL</a>, un appliance con a bordo un sistema operativo, derivante dal fork di <em>pfSense</em>, completamente tradotto in italiano correlato da relativa documentazione.</span></p>';
echo '<p> </p>';
echo '';
echo '';
echo '<p><span style="color:#000000" class="has-inline-color">La scelta nell&#8217;utilizzare la lingua italiana per dei prodotti tecnologici e informatici, visto lo spostamento totale del settore verso le lingue anglofone e nonostante possa sembrare in controtendenza, è ricaduta per rendere più consapevoli gli utilizzatori del &#8220;Bel Paese&#8221; perchè</span>:<span style="color:#000000" class="has-inline-color"> &#8220;r<em>endersi consapevoli significa rafforzare la propria sicurezza ed essere meno esposti ad eventuali minacce</em>&#8220;.</span></p>';
echo '';
echo '';
echo '';
echo '<div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>';
echo '';
echo '';
echo '';
echo '<p style="font-size:12px"><span style="color:#000000" class="has-inline-color"><em>&#8220;</em></span><a rel="noreferrer noopener" href="https://www.insecurenet.com" target="_blank"><em><span style="color:#000000" class="has-inline-color">Insecurenet</span></em></a><span style="color:#000000" class="has-inline-color"><em> è un progetto Vincitore </em></span><a rel="noreferrer noopener" href="http://pingiovani.regione.puglia.it/" target="_blank"><em><span style="color:#000000" class="has-inline-color">PIN</span></em></a><span style="color:#000000" class="has-inline-color"><em> Iniziativa promossa dalle Politiche Giovanili della <a rel="noreferrer noopener" href="https://www.regione.puglia.it/" target="_blank">Regione Puglia</a> e <a rel="noreferrer noopener" href="https://www.arti.puglia.it/" target="_blank">ARTI</a> e finanziata con risorse del FSE PO Puglia 2014 2020 Azione 8 4 e del Fondo per lo Sviluppo e la Coesione&#8221;</em></span></p>';
echo '';
echo '';
echo '';
echo '<figure class="wp-block-image"><img src="https://www.firew4ll.com/wp-content/uploads/2020/06/pin1-1024x180.png" alt=""/></figure>';
echo '';
?>
<?php include("foot.inc");
