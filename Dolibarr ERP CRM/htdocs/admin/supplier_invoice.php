<?php
/* Copyright (C) 2003-2007 Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2013 Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2011 Regis Houssin           <regis.houssin@capnetworks.com>
 * Copyright (C) 2004      Sebastien Di Cintio     <sdicintio@ressource-toi.org>
 * Copyright (C) 2004      Benoit Mortier          <benoit.mortier@opensides.be>
 * Copyright (C) 2010-2013 Juanjo Menent           <jmenent@2byte.es>
 * Copyright (C) 2011-2013 Philippe Grand          <philippe.grand@atoo-net.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
 *  \file       htdocs/admin/supplier_invoice.php
 *  \ingroup    fournisseur
 *  \brief      Setup to admin supplier invoices
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/fourn.lib.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';

$langs->load("admin");
$langs->load("other");
$langs->load("orders");

if (!$user->admin)
accessforbidden();

$type=GETPOST('type', 'alpha');
$value=GETPOST('value', 'alpha');
$action=GETPOST('action', 'alpha');

$specimenthirdparty=new Societe($db);
$specimenthirdparty->initAsSpecimen();


/*
 * Actions
 */

if ($action == 'updateMask')
{
    $maskconstinvoice=GETPOST('maskconstinvoice','alpha');
    $maskvalue=GETPOST('maskinvoice','alpha');

    if ($maskconstinvoice)  $res = dolibarr_set_const($db,$maskconstinvoice,$maskvalue,'chaine',0,'',$conf->entity);

    if (! $res > 0) $error++;

	if (! $error)
	{
		setEventMessage($langs->trans("SetupSaved"));
	}
	else
	{
		setEventMessage($langs->trans("Error"),'errors');
	}
}

if ($action == 'specimen')  // For invoices
{
    $modele=GETPOST('module','alpha');

    $facture = new FactureFournisseur($db);
    $facture->initAsSpecimen();
    $facture->thirdparty=$specimenthirdparty;    // Define who should has build the invoice (so the supplier)

    // Search template files
    $file=''; $classname=''; $filefound=0;
    $dirmodels=array_merge(array('/'),(array) $conf->modules_parts['models']);
    foreach($dirmodels as $reldir)
    {
    	$file=dol_buildpath($reldir."core/modules/supplier_invoice/pdf/pdf_".$modele.".modules.php",0);
    	if (file_exists($file))
    	{
    		$filefound=1;
    		$classname = "pdf_".$modele;
    		break;
    	}
    }

    if ($filefound)
    {
    	require_once $file;

    	$module = new $classname($db,$facture);

    	if ($module->write_file($facture,$langs) > 0)
    	{
    		header("Location: ".DOL_URL_ROOT."/document.php?modulepart=facture_fournisseur&file=SPECIMEN.pdf");
    		return;
    	}
    	else
    	{
    		setEventMessage($module->error,'errors');
    		dol_syslog($module->error, LOG_ERR);
    	}
    }
    else
    {
    	setEventMessage($langs->trans("ErrorModuleNotFound"),'errors');
    	dol_syslog($langs->trans("ErrorModuleNotFound"), LOG_ERR);
    }
}

// Activate a model
else if ($action == 'set')
{
	$ret = addDocumentModel($value, $type, $label, $scandir);
}

else if ($action == 'del')
{
	$ret = delDocumentModel($value, $type);
	if ($ret > 0)
	{
        if ($conf->global->INVOICE_SUPPLIER_ADDON_PDF == "$value") dolibarr_del_const($db, 'INVOICE_SUPPLIER_ADDON_PDF',$conf->entity);
	}
}

// Set default model
else if ($action == 'setdoc')
{
	if (dolibarr_set_const($db, "INVOICE_SUPPLIER_ADDON_PDF",$value,'chaine',0,'',$conf->entity))
	{
		// La constante qui a ete lue en avant du nouveau set
		// on passe donc par une variable pour avoir un affichage coherent
		$conf->global->INVOICE_SUPPLIER_ADDON_PDF = $value;
	}

	// On active le modele
	$ret = delDocumentModel($value, $type);
	if ($ret > 0)
	{
		$ret = addDocumentModel($value, $type, $label, $scandir);
	}
}

if ($action == 'setmod')
{
    // TODO Verifier si module numerotation choisi peut etre active
    // par appel methode canBeActivated

    dolibarr_set_const($db, "INVOICE_SUPPLIER_ADDON_NUMBER",$value,'chaine',0,'',$conf->entity);
}

if ($action == 'addcat')
{
    $fourn = new Fournisseur($db);
    $fourn->CreateCategory($user,$_POST["cat"]);
}

if ($action == 'set_SUPPLIER_INVOICE_FREE_TEXT')
{
    $freetext = GETPOST('SUPPLIER_INVOICE_FREE_TEXT');	// No alpha here, we want exact string

    $res = dolibarr_set_const($db, "SUPPLIER_INVOICE_FREE_TEXT",$freetext,'chaine',0,'',$conf->entity);

    if (! $res > 0) $error++;

	if (! $error)
	{
		setEventMessage($langs->trans("SetupSaved"));
	}
	else
	{
		setEventMessage($langs->trans("Error"),'errors');
	}
}


/*
 * View
 */

$dirmodels=array_merge(array('/'),(array) $conf->modules_parts['models']);

llxHeader("","");

$form=new Form($db);

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("SuppliersSetup"),$linkback,'setup');

print "<br>";

$head = supplierorder_admin_prepare_head(null);

dol_fiche_head($head, 'invoice', $langs->trans("ModuleSetup"));


// Supplier invoice numbering module

print_titre($langs->trans("SuppliersInvoiceNumberingModel"));

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td width="100">'.$langs->trans("Name").'</td>';
print '<td>'.$langs->trans("Description").'</td>';
print '<td>'.$langs->trans("Example").'</td>';
print '<td align="center" width="60">'.$langs->trans("Status").'</td>';
print '<td align="center" width="16">'.$langs->trans("ShortInfo").'</td>';
print "</tr>\n";

clearstatcache();

foreach ($dirmodels as $reldir)
{
	$dir = dol_buildpath($reldir."core/modules/supplier_invoice/");

    if (is_dir($dir))
    {
        $handle = opendir($dir);
        if (is_resource($handle))
        {
            $var=true;

            while (($file = readdir($handle))!==false)
            {
                if (substr($file, 0, 24) == 'mod_facture_fournisseur_' && substr($file, dol_strlen($file)-3, 3) == 'php')
                {
                    $file = substr($file, 0, dol_strlen($file)-4);

                    require_once $dir.$file.'.php';

                    $module = new $file;

                    if ($module->isEnabled())
                    {
                        // Show modules according to features level
                        if ($module->version == 'development'  && $conf->global->MAIN_FEATURES_LEVEL < 2) continue;
                        if ($module->version == 'experimental' && $conf->global->MAIN_FEATURES_LEVEL < 1) continue;

                        $var=!$var;
                        print '<tr '.$bc[$var].'><td>'.$module->nom."</td><td>\n";
                        print $module->info();
                        print '</td>';

                        // Show example of numbering model
                        print '<td class="nowrap">';
                        $tmp=$module->getExample();
                        if (preg_match('/^Error/',$tmp)) {
                            $langs->load("errors"); print '<div class="error">'.$langs->trans($tmp).'</div>';
                        }
                        elseif ($tmp=='NotConfigured') print $langs->trans($tmp);
                        else print $tmp;
                        print '</td>'."\n";

                        print '<td align="center">';
                        if ($conf->global->INVOICE_SUPPLIER_ADDON_NUMBER == "$file")
                        {
                            print img_picto($langs->trans("Activated"),'switch_on');
                        }
                        else
                        {
                            print '<a href="'.$_SERVER["PHP_SELF"].'?action=setmod&amp;value='.$file.'" alt="'.$langs->trans("Default").'">'.img_picto($langs->trans("Disabled"),'switch_off').'</a>';
                        }
                        print '</td>';

                        $invoice=new FactureFournisseur($db);
                        $invoice->initAsSpecimen();

                        // Info
                        $htmltooltip='';
                        $htmltooltip.=''.$langs->trans("Version").': <b>'.$module->getVersion().'</b><br>';
                        $nextval=$module->getNextValue($mysoc,$invoice);
                        if ("$nextval" != $langs->trans("NotAvailable"))	// Keep " on nextval
                        {
                            $htmltooltip.=''.$langs->trans("NextValue").': ';
                            if ($nextval)
                            {
                                $htmltooltip.=$nextval.'<br>';
                            }
                            else
                            {
                                $htmltooltip.=$langs->trans($module->error).'<br>';
                            }
                        }

                        print '<td align="center">';
                        print $form->textwithpicto('',$htmltooltip,1,0);
                        print '</td>';

                        print '</tr>';
                    }
                }
            }
            closedir($handle);
        }
    }
}

print '</table><br>';




/*
 * Modeles documents for supplier invoices
 */

print_titre($langs->trans("BillsPDFModules"));

// Defini tableau def de modele
$def = array();

$sql = "SELECT nom";
$sql.= " FROM ".MAIN_DB_PREFIX."document_model";
$sql.= " WHERE type = 'invoice_supplier'";
$sql.= " AND entity = ".$conf->entity;

$resql=$db->query($sql);
if ($resql)
{
    $i = 0;
    $num_rows=$db->num_rows($resql);
    while ($i < $num_rows)
    {
        $array = $db->fetch_array($resql);
        array_push($def, $array[0]);
        $i++;
    }
}
else
{
    dol_print_error($db);
}

print '<table class="noborder" width="100%">'."\n";
print '<tr class="liste_titre">'."\n";
print '<td width="100">'.$langs->trans("Name").'</td>'."\n";
print '<td>'.$langs->trans("Description").'</td>'."\n";
print '<td align="center" width="60">'.$langs->trans("Status").'</td>'."\n";
print '<td align="center" width="60">'.$langs->trans("Default").'</td>'."\n";
print '<td align="center" width="40">'.$langs->trans("ShortInfo").'</td>';
print '<td align="center" width="40">'.$langs->trans("Preview").'</td>';
print '</tr>'."\n";

clearstatcache();

foreach ($dirmodels as $reldir)
{
	$dir = dol_buildpath($reldir."core/modules/supplier_invoice/pdf/");

    if (is_dir($dir))
    {
        $var=true;

        $handle=opendir($dir);


        if (is_resource($handle))
        {
            while (($file = readdir($handle))!==false)
            {
                if (preg_match('/\.modules\.php$/i',$file) && substr($file,0,4) == 'pdf_')
                {
                    $name = substr($file, 4, dol_strlen($file) -16);
                    $classname = substr($file, 0, dol_strlen($file) -12);

                    $var=!$var;
                    print "<tr ".$bc[$var].">\n";
                    print "<td>".$name."</td>\n";
                    print "<td>\n";
                    require_once $dir.$file;
                    $module = new $classname($db,$specimenthirdparty);
                    print $module->description;
                    print "</td>\n";

                    // Active
                    if (in_array($name, $def))
                    {
                        print '<td align="center">'."\n";
                        if ($conf->global->INVOICE_SUPPLIER_ADDON_PDF != "$name")
                        {
                            print '<a href="'.$_SERVER["PHP_SELF"].'?action=del&amp;value='.$name.'&amp;scandir='.$module->scandir.'&amp;label='.urlencode($module->name).'&amp;type=invoice_supplier">';
                            print img_picto($langs->trans("Enabled"),'switch_on');
                            print '</a>';
                        }
                        else
                        {
                            print img_picto($langs->trans("Enabled"),'switch_on');
                        }
                        print "</td>";
                    }
                    else
                    {
                        print '<td align="center">'."\n";
                        print '<a href="'.$_SERVER["PHP_SELF"].'?action=set&amp;value='.$name.'&amp;scandir='.$module->scandir.'&amp;label='.urlencode($module->name).'&amp;type=invoice_supplier">'.img_picto($langs->trans("Disabled"),'switch_off').'</a>';
                        print "</td>";
                    }

                    // Default
                    print '<td align="center">';
                    if ($conf->global->INVOICE_SUPPLIER_ADDON_PDF == "$name")
                    {
                        print img_picto($langs->trans("Default"),'on');
                    }
                    else
                    {
                        print '<a href="'.$_SERVER["PHP_SELF"].'?action=setdoc&amp;value='.$name.'&amp;scandir='.$module->scandir.'&amp;label='.urlencode($module->name).'&amp;type=invoice_supplier"" alt="'.$langs->trans("Default").'">'.img_picto($langs->trans("Disabled"),'off').'</a>';
                    }
                    print '</td>';

                    // Info
                    $htmltooltip =    ''.$langs->trans("Name").': '.$module->name;
                    $htmltooltip.='<br>'.$langs->trans("Type").': '.($module->type?$module->type:$langs->trans("Unknown"));
                    $htmltooltip.='<br>'.$langs->trans("Width").'/'.$langs->trans("Height").': '.$module->page_largeur.'/'.$module->page_hauteur;
                    $htmltooltip.='<br><br><u>'.$langs->trans("FeaturesSupported").':</u>';
                    $htmltooltip.='<br>'.$langs->trans("Logo").': '.yn($module->option_logo,1,1);
                    $htmltooltip.='<br>'.$langs->trans("PaymentMode").': '.yn($module->option_modereg,1,1);
                    $htmltooltip.='<br>'.$langs->trans("PaymentConditions").': '.yn($module->option_condreg,1,1);
                    print '<td align="center">';
                    print $form->textwithpicto('',$htmltooltip,1,0);
                    print '</td>';
                    print '<td align="center">';
                    print '<a href="'.$_SERVER["PHP_SELF"].'?action=specimen&amp;module='.$name.'">'.img_object($langs->trans("Preview"),'order').'</a>';
                    print '</td>';

                    print "</tr>\n";
                }
            }

            closedir($handle);
        }
    }
}

print '</table><br/>';
print '<br>';

/*
 * Other options
 *
 */

print_titre($langs->trans("OtherOptions"));
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td align="center" width="60">'.$langs->trans("Value").'</td>';
print '<td width="80">&nbsp;</td>';
print "</tr>\n";

print '<form action="'.$_SERVER["PHP_SELF"].'" method="post">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SUPPLIER_INVOICE_FREE_TEXT">';
print '<tr '.$bc[$var].'><td colspan="2">';
print $langs->trans("FreeLegalTextOnInvoices").' ('.$langs->trans("AddCRIfTooLong").')<br>';
print '<textarea name="SUPPLIER_INVOICE_FREE_TEXT" class="flat" cols="120">'.$conf->global->SUPPLIER_INVOICE_FREE_TEXT.'</textarea>';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print "</td></tr>\n";
print '</form>';

$db->close();
llxFooter();
?>
