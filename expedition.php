<?php
/* Copyright (C) 2004      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2006 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Regis Houssin        <regis@dolibarr.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
        \file       htdocs/comm/propal/info.php
        \ingroup    propale
		\brief      Page d'affichage des infos d'une proposition commerciale
		\version    $Id: info.php,v 1.34 2011/08/03 00:46:34 eldy Exp $
*/

include 'config.php';

require_once(DOL_DOCUMENT_ROOT."/core/lib/images.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/files.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formfile.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/extrafields.class.php");
require_once(DOL_DOCUMENT_ROOT."/contact/class/contact.class.php");

require_once(DOL_DOCUMENT_ROOT."/expedition/class/expedition.class.php");
require_once(DOL_DOCUMENT_ROOT."/product/class/html.formproduct.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/product.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/sendings.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/modules/expedition/modules_expedition.php");
if ($conf->product->enabled || $conf->service->enabled)  require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");
if ($conf->propal->enabled)   require_once(DOL_DOCUMENT_ROOT."/comm/propal/class/propal.class.php");
if ($conf->commande->enabled) require_once(DOL_DOCUMENT_ROOT."/commande/class/commande.class.php");
if ($conf->stock->enabled)    require_once(DOL_DOCUMENT_ROOT."/product/stock/class/entrepot.class.php");


$langs->load("sendings");
$langs->load("companies");
$langs->load("bills");
$langs->load('deliveries');
$langs->load('orders');
$langs->load('stocks');
$langs->load('other');
$langs->load('propal');


$id = isset($_REQUEST["id"])?$_REQUEST["id"]:'';

// Security check
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'societe', $socid);


/*
 *	View
 */

llxHeader();

$exp = new Expedition($db);
$exp->fetch($_REQUEST["id"]);
$exp->fetchObjectLinked();

// Pour la gestion des contacts (les contacts liés à l'expedition sont les même que la commande)
if($exp->origin == 'commande') {
	$cde = new Commande($db);
	$cde->fetch($exp->origin_id);
}

$societe = new Societe($db);
$societe->fetch($exp->socid);

$head = shipping_prepare_head($exp);
dol_fiche_head($head, 'tabEditions6', $langs->trans("Sending"), 0, 'sending');

require('./class/odt.class.php');
require('./class/atm.doctbs.class.php');

if(isset($_REQUEST['action']) && $_REQUEST['action']=='GENODT') {

	$fOut =  $conf->expedition->dir_output . '/sending/'. dol_sanitizeFileName($exp->ref).'/'.dol_sanitizeFileName($exp->ref).'-'.$_REQUEST['modele']/*. TODTDocs::_ext( $_REQUEST['modele'])*/;

	$tableau=array();
	
	foreach($exp->lines as $ligne) {
		$ligneArray = TODTDocs::asArray($ligne);
		if(empty($ligneArray['product_label'])) $ligneArray['product_label'] = $ligneArray['description'];
		if(empty($ligneArray['product_ref'])) $ligneArray['product_ref'] = '';
		if($ligneArray['remise_percent'] == 0) $ligneArray['remise_percent'] = '';
		$tableau[]=$ligneArray;
	}
	
	$contact = TODTDocs::getContact($db, $cde, $societe);
	if(isset($contact['SHIPPING'])) {
		$societe->nom = $contact['SHIPPING']['societe'];
		if($contact['SHIPPING']['address'] != '') {
			$societe->address = $contact['SHIPPING']['address'];
			$societe->cp = $contact['SHIPPING']['cp'];
			$societe->ville = $contact['SHIPPING']['ville'];
			$societe->pays = $contact['SHIPPING']['pays'];
		}
	}
	
	TODTDocs::makeDocTBS(
		'expedition'
		, $_REQUEST['modele']
		,array('doc'=>$exp, 'societe'=>$societe, 'mysoc'=>$mysoc, 'conf'=>$conf, 'tableau'=>$tableau, 'contact'=>$contact, 'linkedObjects'=>$exp->linkedObjects)
		,$fOut
		, $conf->entity
		,isset($_REQUEST['btgenPDF'])
	);
	
	
}

?>
<form name="genfile" method="get" action="<?=$_SERVER['PHP_SELF'] ?>">
	<input type="hidden" name="id" value="<?=$id ?>" />
	<input type="hidden" name="action" value="GENODT" />
<table width="100%"><tr><td>
<?


?>Modèle à utiliser* <?
TODTDocs::combo('expedition', 'modele',GETPOST('modele'), $conf->entity);
?> <input type="submit" value="Générer" class="button" name="btgen" /> <input type="submit" id="btgenPDF"  name="btgenPDF" value="Générer en PDF" class="button" /><?

?><br><small>* parmis les formats OpenDocument (odt, ods) et Microsoft&reg; office xml (docx, xlsx)</small>
	<p><hr></p>
	<?
	
TODTDocs::show_docs($db, $conf,$exp, $langs, 'expedition');


?>
</td></tr></table>
</form>

<?
print '</div>';
$db->close();

llxFooter('$Date: 2011/08/03 00:46:34 $ - $Revision: 1.34 $');


?>