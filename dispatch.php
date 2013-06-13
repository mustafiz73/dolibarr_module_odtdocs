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
require_once(DOL_DOCUMENT_ROOT."/custom/dispatch/lib/dispatch.lib.php");
require_once(DOL_DOCUMENT_ROOT."/custom/dispatch/class/dispatch.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/modules/expedition/modules_expedition.php");
require_once(DOL_DOCUMENT_ROOT."/custom/asset/class/asset.class.php");
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

global $db;
$id = isset($_REQUEST["id"])?$_REQUEST["id"]:'';

// Security check
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'societe', $socid);

/*
 *	View
 */

llxHeader();


function __poids_unite($unite){
	switch ($unite) {
		case -6:
			return('mg');
			break;
		case -3:
			return('g');
			break;
		case 0:
			return('kg');
			break;
	}

}


$ATMdb = new Tdb;
$dispatch = new TDispatch;
$dispatch->load($ATMdb,$_REQUEST["id"]);

$commande = new Commande($db);
$commande->fetch($_REQUEST['fk_commande']);

$societe = new Societe($db);
$societe->fetch($commande->socid);

$head = dispatch_prepare_head($commande,$dispatch);
dol_fiche_head($head, 'delivery', $langs->trans("Sending"), 0, 'sending');

require('./class/odt.class.php');
require('./class/atm.doctbs.class.php');

if(isset($_REQUEST['action']) && $_REQUEST['action']=='GENODT') {
	
	$fOut =  $conf->expedition->dir_output . '/sending/'. dol_sanitizeFileName($dispatch->ref).'/'.dol_sanitizeFileName($dispatch->ref).'-'.$_REQUEST['modele']/*. TODTDocs::_ext( $_REQUEST['modele'])*/;
	
	$tableau=array();
	
	//Parcours des lignes de la commande
	foreach($commande->lines as $cligne) {
		$dispatch = new TDispatch;
		$dispatch->load($ATMdb,$id);
		//Chargement des ligne d'équipement associé à la ligne de commande
		$dispatch->loadLines($ATMdb,$cligne->rowid);
		
		foreach($dispatch->lines as $dligne){
			$ligneArray = TODTDocs::asArray($dligne);
			
			//Chargement de l'équipement lié à la ligne d'expédition
			$TAsset = new TAsset;
			$TAsset->load($ATMdb,$dligne->fk_asset);
			
			//Chargement du produit lié à l'équipement
			$product = new Product($db);
			$product->fetch($TAsset->fk_product);
			
			$ligneArray['product_ref'] = $product->ref;
			$ligneArray['product_label'] = $product->label;
			$ligneArray['asset_lot'] = $TAsset->lot_number;
			$ligneArray['weight_unit'] = __poids_unite($ligneArray['weight_unit']);
			$ligneArray['tare_unit'] = __poids_unite($ligneArray['tare_unit']);
			$ligneArray['weight_reel_unit'] = __poids_unite($ligneArray['weight_reel_unit']);
			
			$tableau[]=$ligneArray;
		}
	}
	
	$contact = TODTDocs::getContact($db, $commande, $societe);
	if(isset($contact['SHIPPING'])) {
		$societe->nom = $contact['SHIPPING']['societe'];
		if($contact['SHIPPING']['address'] != '') {
			$societe->address = $contact['SHIPPING']['address'];
			$societe->cp = $contact['SHIPPING']['cp'];
			$societe->ville = $contact['SHIPPING']['ville'];
			$societe->pays = $contact['SHIPPING']['pays'];
		}
	}
	
	$autre = array(
		'date_jour' => date("d/m/Y H:i:s")
		);
	
	echo '<pre>';
	print_r($commande->linkedObjects);
	echo '</pre>';
	
	TODTDocs::makeDocTBS(
		'expedition'
		, $_REQUEST['modele']
		,array('doc'=>$commande, 'societe'=>$societe, 'mysoc'=>$mysoc, 'conf'=>$conf, 'tableau'=>$tableau, 'contact'=>$contact, 'linkedObjects'=>$commande->linkedObjects, 'dispatch'=>$dispatch, 'autre'=>$autre)
		,$fOut
		, $conf->entity
		,isset($_REQUEST['btgenPDF'])
	);
}

?>
<form name="genfile" method="get" action="<?=$_SERVER['PHP_SELF'] ?>">
	<input type="hidden" name="id" value="<?=$id ?>" />
	<input type="hidden" name="fk_commande" value="<?=$commande->id ?>" />
	<input type="hidden" name="action" value="GENODT" />
<table width="100%"><tr><td>
<?


?>Modèle à utiliser* <?
TODTDocs::combo('expedition', 'modele',GETPOST('modele'), $conf->entity);
?> <input type="submit" value="Générer" class="button" name="btgen" /> <input type="submit" id="btgenPDF"  name="btgenPDF" value="Générer en PDF" class="button" /><?

?><br><small>* parmis les formats OpenDocument (odt, ods) et Microsoft&reg; office xml (docx, xlsx)</small>
	<p><hr></p>
	<?
	
TODTDocs::show_docs($db, $conf,$dispatch, $langs, 'expedition');


?>
</td></tr></table>
</form>

<?
print '</div>';
$db->close();

llxFooter('$Date: 2011/08/03 00:46:34 $ - $Revision: 1.34 $');


?>