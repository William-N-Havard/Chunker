<?php 
header('Content-Type: text/html; charset=utf-8');

//on récupère les valeurs de paramètrage
    
    //on définit ce qui se passe après la fermeture d'un chunk, soit retour à la ligne, soit rien
    if(isset($_POST['apresChunk'])){
        $apresChunk="<br>\n";
    }else{
        $apresChunk="";
    }
    
    //on récupère les caractères d'ouverture et de fermeture de chunk ; s'ils sont vides, on leur donne une valeur par défaut
    if(!empty($_POST['ouvreChunk'])){
            $baliseO=$_POST['ouvreChunk'];
    }else{
            $baliseO="[";
    }

    if(!empty($_POST['fermeChunk']))    {
            $baliseF=$_POST['fermeChunk'];
    }else{
            $baliseF="]";
    }
    
    //on récupère le label d'un chunk inconnu, si vide on lui donne une valeur par défaut
    if(!empty($_POST['labelChunkInconnu'])){
        $labelChunkInconnu=$_POST['labelChunkInconnu'];
    }else{
        $labelChunkInconnu='INC';
    }
    
    //on récupère le contenu de "normalisation" qui permet de changer certains caractères dans le texte ex.’=>'
    if(!empty($_POST['normalisation'])){
        $norm=$_POST['normalisation'];
    }else{
        $norm=false;
    }
    
    //on récupère le contenu de "recombi" qui permet de recombiner les tokens entre eux
    if(!empty($_POST['recombi'])){
        $recombinaisons=$_POST['recombi'];
        $combin=recombinaison($recombinaisons);
    }else{
        $combin=array();
        $recombinaisons='';
    }
    
    if(!empty($_POST['carTok'])){
        $carTok=$_POST['carTok']; //permettra de remettre les caractères séparateurs dans la boîte d'input            
    }else{
        $carTok=' '; //sinon le caractère séparateur de token est l'espace
    }
    
if(!empty($_POST['texte']))
{   	
    $time_start = microtime_float(); //permettra d'afficher le temps nécéssaire à l'analyse des chunks
    
    $erreur=array();    //tableau qui servira à stocker les erreurs
    $chunkInconnus=0;   //variable qui permet de compter le nombre de chunks de nature inconnue
    $nbChunk=0;         //permet de compter le nombre de chunks
    
    $sortie="";         //ce qui sera affiché dans la div de résultat
    $ouvert=0;          //permet de savoir si un chunk a été ouvert, par défaut "non"

    
    /////////////////////////////////////////////////////
    //                                                 //
    //              OPERATIONS SUR LE TEXTE            //
    // (normalisation, tokenisation, recombinaisons)   //
    //                                                 //
    /////////////////////////////////////////////////////
    
    //on récupère le texte à chunker
    $texte=$_POST['texte'];
    
    //on le normalise en fonction de ce que l'utilisateur a rentré (ou on ne fait rien s'il ne souhaite aucune normalisation)
    if($norm!=false)
    {
        $texte=normalisation($norm, $texte);	
    }
    
    //on tokenise le texte en fonction des caractères séparateurs donnés par l'utilisateur ; si rien n'est indiqué, le caractère séparateur par défaut est l'espace
    //on le fait grâce à preg_split et au flag PREG_SPLIT_DELIM_CAPTURE qui permet de garder les caractères séparateurs (nécessaire car on souhaite garder ponctuation et apostrophes)
    $tok=preg_split('/(['.preg_quote($carTok).'])/u', $texte, -1, PREG_SPLIT_DELIM_CAPTURE);
    
    //on initialise le tableau dans lequel seront stockés les tokens
    $tokens = array();
    for ($i=0, $n=count($tok); $i<$n; $i++)
    {
        //on recombine les tokens en fonction des recombinaisons voulues par l'utilisateur
        if(isset($tok[$i+1]) && array_key_exists($tok[$i+1], $combin) && $combin[$tok[$i+1]]=="<")
        {
            $tokens[] = trim($tok[$i]).trim($tok[$i+1]);
            $i++;
        }
        elseif(isset($tok[$i+1]) && array_key_exists($tok[$i], $combin) && $combin[$tok[$i]]==">")
        {
            $tokens[] = trim($tok[$i]).trim($tok[$i+1]);
            $i++;
        }
        //sinon on remplit juste le tableau des tokens
        else
        {
            $tokens[] = trim($tok[$i]);
        }
    }    
    //on enlève les éléments vides du tableau (les anciens espaces) et on recalcule les clefs pour qu'elles soient contiguës
    $tokens=array_values(array_filter($tokens));

    
    /////////////////////////////////////////////////////
    //                                                 //
    //     OPERATIONS SUR LES CLASSES ET REGLES        //
    //                                                 //
    /////////////////////////////////////////////////////    
    
    //on récupère et on traite le contenu du textarea 'regles'
    $classes_regles=explode("\n",$_POST['regles']);
    
    //variables utilisées pour stocker les classes et les règles
    $cat=array();
    $regles=array();

    foreach($classes_regles as $ligne_classe_regle_entree)
    {
        //on supprime les éventuelles espaces qui peuvent poser problème
        $traitement_ligne_regles_classes=trim($ligne_classe_regle_entree);
        //si la ligne commence par #, c'est qu'il s'agit d'un commentaire
        if(substr($traitement_ligne_regles_classes, 0, 1)!="#")
        {   
            //la fonction 'preg_split' suivante permet de séparer les classes/règles lorsqu'il y a un ';' qui n'est pas précédé de \
            if(count($classe_regle_a_traiter=preg_split("/(?<!\\\);/", $traitement_ligne_regles_classes))>1)
            {
                foreach($classe_regle_a_traiter as $traitement_classe_regle)
                {
                    //si on a := c'est qu'il s'agit d'une classe
                    if(strpos($traitement_classe_regle, ":="))
                    {
                        $categorie=explode(":=", $traitement_classe_regle);
                        //le 'if' suivant évite de générer une erreur si la ligne est vide ou s'il n'y a rien 
                        if(isset($categorie[0], $categorie[1]) && !empty($categorie[0]) && !empty($categorie[1]))
                        {
                            //on explose $categorie[1] pour avoir le lexique
                            $lexique=explode(" ", $categorie[1]);
                            foreach($lexique as $stockage)
                            {
                                $cat[trim($categorie[0])][]=trim($stockage);
                            }
                        }
                    }
                    //si l'on a seulement =, c'est que c'est une règle
                    elseif(strpos($traitement_classe_regle, "="))
                    {
                        $reg=explode("=", $traitement_classe_regle);
                        //le if suivant évite de générer une erreur si la ligne est vide
                        if(isset($reg[0], $reg[1]) && !empty($reg[0]) && !empty($reg[1]))
                        {
                            //$regles[0][] => conditions
                            $regles[0][]=trim($reg[0]);
                            //$regles[1][] => actions
                            $regles[1][]=trim($reg[1]);
                            //permettra d'incrémenter le nombre de fois qu'une règle est utilisée
                            $regles[2][]=0;
                        }
                    }                       
                }
            }          
        }		
    }
    
    /////////////////////////////////////////////////////
    //                                                 //
    //                     CHUNKING                    //
    //                                                 //
    /////////////////////////////////////////////////////   

    //pour chaque token
    for($t=0; $t<count($tokens); $t++)
    {
       //pour chaque règle
       for($r=0; $r<count($regles[0]); $r++)
        {
            //permet de dire si on a pu appliquer quelque chose
            $app=0; 
            
            //on sépare les conditions de la règle que l'on étudie
            //le preg_split suivant permet de ne séparer qu'aux + sauf s'ils sont dans une expression régulière
        	//vu le peu de règles que l'on a actuellement, la regex suivante marche mais elle serait certainement à raffiner un peu
            $conditions=preg_split("/\+((?=(\s*)?\")|(?=(\s*)?[a-zA-Z])|(?=(\s*?)\?)|(?=(\s*?)#)|(?=(\s*?)\\\))/", $regles[0][$r]);
            //on récupère les actions à effectuer (ici pas de problème avec les +)
            $actions=explode("+", $regles[1][$r]);		
			
            //pour chacune des conditions
            for($c=0; $c<count($conditions); $c++)
            {
                //on regarde s'il y a une condition négative (# sauf si antislashé)
                $get_conditions=preg_split("/(?<!\\\)(#)/", $conditions[$c], -1, PREG_SPLIT_DELIM_CAPTURE);
                
                $cond_neg=array();
                for ($j=0, $k=count($get_conditions); $j<$k; $j+=1)
                {
                    //si on a un # (symbole d'une condition négative), on le raccroche à ce qui vient après et on rajoute le tout au tableau des conditions
                    if(isset($get_conditions[$j+1]) && ($get_conditions[$j]=="#"))
                    {
                        $cond_neg[] = "#".trim($get_conditions[$j+1]);
                        $j++;
                    }
                    //sinon on remplit juste le tableau des conditions
                    else    
                    {
                        $cond_neg[] = trim($get_conditions[$j]);
                    }   
                }
                $cond_neg=array_values(array_filter($cond_neg));                    
                
                //on parcourt chacune des conditions (ou sous-conditions si jamais on a détecté un #)
                for($c_n=0; $c_n<count($cond_neg); $c_n++)
                {
                    $neg=false;    
                    //si jamais la condition commence par un #, on le retire afin de pouvoir analyser la condition
                    //pour ne pas oublier qu'il s'agit d'une négation, on fait passer $neg à vrai
                    if(substr($cond_neg[$c_n], 0, 1)=="#")
                    {
                        $cond_neg[$c_n]=substr($cond_neg[$c_n], 1, strlen($cond_neg[$c_n])-1);
                        $neg=true;
                    }
					
                    if(!isset($tokens[$t+$c]))//si jamais le token n'existe pas, on sort
                    {
                            break 2;
                    }
					
                    if(tester_condition($tokens[$t+$c], $cond_neg[$c_n]) && $neg==true)
                    {
                        //si la condition n'est pas remplie on sort
                        break 2;
                    }
                    elseif(!tester_condition($tokens[$t+$c], $cond_neg[$c_n]) && $neg==false)
                    {
                        break 2;
                    }                       
                }                
            }
            
            //si $c est égal au nombre de conditions, c'est qu'elles ont toutes été remplies
            if($c==count($conditions))
            {                  
                //on applique les actions
                $sortie.=application($conditions, $actions, $t, $r);
                //on dit qu'on a réussi à appliquer
                $app=1;
                //on saute d'autant de tokens qu'il y avait de conditions -1 (car on enlève celui que l'on étudiait)
                $t+=$c-1;
                //on incrémente le nombre de fois que la règle est utilisée
                $regles[2][$r]++;
                break;
            }
        }
         
        //si rien n'a pu être appliqué
        if($app==0)
        {
            if($ouvert==0)
            {
                //si rien n'est ouvert, c'est qu'on a affaire à un chunk de type inconnu, il faut donc ouvrir un chunk de ce type
                $sortie.='<sub><strong style="color:red;">'.$labelChunkInconnu.'</strong></sub>'.$baliseO;
                $chunkInconnus++;
                changement_ouverture();
            }
            //on rajoute le token lu à la sortie
            $sortie.=$tokens[$t].' ';
        }        
    }
    
    //si un chunk est ouvert, on le ferme (dernier chunk du texte)
    if($ouvert==1)
    {
        $sortie.=$baliseF.$apresChunk;
        changement_ouverture();
    }
        
    //on affecte leur valeurs aux sorties
    $sortie=trim($sortie);
    $valRegles=$_POST['regles'];
    $valTexte=$_POST['texte'];
	
	
    $time_end = microtime_float();
    $time = $time_end - $time_start;
}
else //valeurs par défaut si rien n'a été rempli
{
    $valRegles="# Classes
ADVphr:=autrefois plutôt pourtant;
CCO:=mais ou et or car;
CjQUE:=que qu';
CLT:=me te le la les nous vous lui leur y en;
CS:=comme comment lorsque lorsqu' puisque quand si;
Det:=le la l' les un une des mon ma mes ton ta tes son sa ses notre nos votre vos leur leurs;
INT:=qui que qu' quel quels quelle quelles quand quoi pourquoi où comment lequel laquelle lesquels lesquelles auquel auxquels auxquelles duquel desquels desquels;
INTJ:=oh nb;
MOD:=suis es est sommes êtes sont serai seras sera serons serez seront étais était étions étiez étaient sois soit soyons soyez soient serais serait serions seriez seraient ai as a avons avez ont aurai auras aura aurons aurez auront avais avait avions aviez avaient aie aies ait ayons ayez aient aurais aurait aurions auriez auraient;
N:=ateliers ce colloques force humanités;
NEG:=n' ne ni;
NUM:=un une deux trois quatre cinq six sept huit neuf dix;
PCTNF:=, : « » ( ) \; ;
PCTF:=. ? !;
PPS:=je j' tu il elle on nous vous ils elles ça c' ce;
Prep:=à après avant avec au aux d' dans de depuis derrière des devant du en entre jusqu' jusque outre par pendant pour sans selon sur sous vers;
PrepV:=en;
PrInd:=aucun aucune autre autres nul nulle nuls nulles personne plusieurs rien tout un une;
REL:=dont pourquoi où quand qui lequel laquelle lesquels lesquelles auquel auxquels auxquelles duquel desquels desquels;
V:=devenant éviter exploiter faire regardez remontent reviennent suivent;

# Règles (ordre important)
\"/A/\" + \"/^[A-Z]/\" = (P) _ + _;
PrepV + \"/ant$/\" = (Vgér) _ + _;
Prep + Prep + ? = (P) _ + _ + _;
Prep + ? = (P) _ + _;
\"/(l'|d')/\" + PrInd = (PRO) _ + _;
\"/(I|i)l/\" + \"/y/\" + MOD = (SV) _ + _ + _;
PPS + ? = (SV) _ + _;
NEG + V = (SV) _ + _;
CLT + V = (SV) _ + _;
V = (SV) _;
MOD = (MOD) _;
NEG + MOD = (MOD) _ + _;
\"l'\" + \"on\" + ? = (SV) _ + _ + _;
Det + ? = (N) _ + _;
NUM = (N) _;
N = (N) _;
\"/^M$/\" + \"/\./\" + ?= (N) _ +  _ + _;
\"/^M(m|ll)?e$/\" + ? = (N) _ + _;
INT + \"/est-ce/\" + CjQUE = (INT) _ +  _ + _;
\"/(E|e)st-ce/\" + CjQUE = (INT) _ + _;
PCTF + Prep + INT = (PCTF) _ + (INT) _ + _;
PCTF + INT = (PCTF) _ + (INT) _;
REL = (REL) _;
CjQUE = (CjQUE) _;
INT = (INT) _;
ADVphr = (ADV) _;
CS =(CS) _;
CCO = (CCO) _ + &;
NEG = (NEG) _;
INTJ = (INTJ) _;
\"/((r)?(ai(en)?t)|((i?)ez))|(r((a(i|s))|(ez)|(on(s|t))))|((â|û|î)(((m|t|r)es)|(t)))$/\" = (SV) _;
PCTNF = (PCTNF) _ + &;
PCTF = (PCTF) _ + &;";
    
    $valTexte="    Il y a dix jours, je revendiquais à la fin d'une présentation à la CPU que le terme humanités numériques est voué à disparaître rapidement, car devenant une pratique courante en sciences humaines, comme autrefois la linguistique de corpus était le terme à la mode, jusqu'à ce qu'elle devienne une pratique normale.
    
    Mais force est de constater que je suis en passe de me tromper. Plutôt que d'hériter de l'expérience de nos pairs linguistes de corpus et éviter de reproduire les mêmes erreurs, nous rempilons sur le même schéma.
    
    Les journées, ateliers et colloques scientifiques à foison sur le thème reviennent toujours sur les mêmes sujets : « regardez comment il est joli mon beau site » et « oh le vilain, il ne suit pas le courant » (nb : il n'y a que les poissons morts qui suivent le courant, les poissons vivants le remontent).
    
    Pourtant, l'objet même des humanités numériques n'est ni le site web ni la norme, mais l'établissement de connaissances sous forme numérique de manière à les explorer, les exploiter et en faire ressortir de nouvelles connaissances profitables à la communauté scientifique. Le jour où l'on entendra « regardez ce que j'ai découvert en tirant profit de mes données », et où l'on débattra sur cette nouvelle connaissance, l'on sera enfin entrés dans l'ère des humanités numériques.";
    $sortie="";
    
    //valeurs par défaut de certains paramètres
    $carTok=" .?!:',();«»\"";
    $baliseO="[";
    $baliseF="]";
    $norm='’=>\';ʼ=>\';';
    $apresChunk=true;
    $recombinaisons="':<;";
    $labelChunkInconnu='INC';
}

function application($conditions, $action, $t, $r)
{
    //permet de récupérer la liste de tokens (juste une lecture dessus, pas de modification)
    global $tokens;
    global $regles;
    
    $sortie="";
    
    //pour chacune des actions
    foreach($action as $key=>$Action)
    {  
        $appAction=trim($Action);
        
        //permet de remplacer (BLABLA) par le nom du chunk puis le caractère d'ouverture de chunk
        if(preg_match("/\((.+)\)/", $appAction, $matches))
        {
            //si un chunk est ouvert, on le ferme
            if($GLOBALS['ouvert']==1)
            {
                $sortie=trim($sortie).$GLOBALS['baliseF'].$GLOBALS['apresChunk'];
                changement_ouverture();
            }
            
            //on affiche le nom du chunk
            $sortie.='<sub><strong>'.$matches[1].'</strong></sub>';
            //si l'utilisateur souhaite voir le numéro des règles, on affiche la règle
            $sortie.=isset($_POST['num'])?' <a href="#" class="info"><sup>'.$r.'</sup><span>'.$regles[0][$r].' = '.$regles[1][$r].'</span></a> ' : ' ';
            //maintenant qu'on a remplacé (BLABLA) par le nom du chunk, on peut enlever (BLABLA) des actions
            $appAction=preg_replace("/\((.+)\)/", '', $appAction);
            //on affiche le caractère d'ouverture de chunk
            $sortie.=$GLOBALS['baliseO'];
            $GLOBALS['nbChunk']++;
            changement_ouverture();
        }   
 
        //on remplace les ____ par la valeur qu'il faut            
        if(preg_match("/_+/", trim($appAction)))
        {   
            if($GLOBALS['ouvert']==0)
            {
                //si rien n'est ouvert, c'est qu'on a affaire à un chunk de type inconnu, il faut donc ouvrir un chunk de ce type
                $sortie.='<sub><strong style="color:red;">'.$GLOBALS['labelChunkInconnu'].'</strong></sub>'.$GLOBALS['baliseO'];
                $GLOBALS['chunkInconnus']++;
                changement_ouverture();
            }
            
            if(isset($conditions[$key]) && isset($_POST['num']))
            {
                $sortie.=preg_replace("/_+/", '<a href="#" class="info">'.$tokens[$t].'<span>'.$conditions[$key].'</span></a> ', $appAction);
            }else{
                $sortie.=preg_replace("/_+/", $tokens[$t].' ', $appAction);
            }              
            $t++;
        }        
        
        //si jamais l'utilisateur a voulu forcer la fermeture d'un chunk
        if($appAction=="&")
        {   
            if(isset($_POST['num']))
            {
                $sortie.='<a href="#" class="info">'. $GLOBALS['baliseF'].'<span>&</span></a>'.$GLOBALS['apresChunk'];
            }else{
                $sortie.=$GLOBALS['baliseF'].$GLOBALS['apresChunk'];
            }  
            changement_ouverture();
        }
    }    
    return $sortie;
}

function tester_condition($token, $condition)
{
    //permet de récupérer la liste des catégories (juste une lecture dessus, pas de modification)
    global $cat;
    //permettra de rajouter des erreurs s'il y a lieu
    global $erreur;

    $condition=trim($condition);  
    
    //si le token est un ; on rajoute un antislash devant pour qu'il soit pris en compte s'il apparaît dans une classe
    if($token==';')
    {
        $token='\;';
    }                                                    
    
    //si jamais la condition est ? c'est que c'est toujours vrai
    if($condition=="?")
    {
        return true;
    }
    //si c'est une expression régulière
    elseif(substr($condition, 0, 2)=='"/' && substr($condition, -2)=='/"')
    {        
        $condition=substr($condition, 1, strlen($condition)-2);
        return preg_match($condition, $token);
    }
    //si c'est la forme graphique
    elseif(substr($condition, 0, 1)=="\"" && substr($condition, -1)=="\"")
    {
        $contenu=substr($condition, 1, strlen($condition)-2);
        if($contenu===$token){
            return true;
        }        
    }
    //sinon c'est une classe
    else
    {
        //on met en minuscule pour normaliser par rapport aux classes
        //ne surtout pas utiliser strtolower sinon bug avec les caractères accentués àéèù etc. 
        $token=mb_strtolower($token, "UTF-8");
        if(array_key_exists($condition, $cat))
        {   
            foreach($cat[$condition] as $contenu)
            {
                if(trim($contenu)==trim($token))
                {
                    return true;
                }
            }
        }
        else
        {   //si la classe n'existe pas, on le met dans le tableau 'erreur' pour avertir l'utilisateur
            $message_erreur='La classe <strong>'.$condition.'</strong> n\'existe pas !';
            if(!in_array($message_erreur, $erreur))
            {
                $erreur[]=$message_erreur;
            }
        }
    }
    return false;
}

function normalisation($normalisation, $texte)
{
    if(trim($normalisation)!='')
    {
        $normalisation_n=preg_split("/(?<!\\\);/", $normalisation); //on explose la chaîne à tous les ';' (sauf s'ils sont échappés)
        //on regarde chaque paire de normalisation
        for($n=0; $n<count($normalisation_n); $n++)
        {
            if($normalisation[$n]!='')
            {
                //on récupère le caractère à normaliser et le caractère par lequel il sera normalisé (séparés par =>)
                $car_a_normaliser=explode("=>",$normalisation_n[$n]);
                if($car_a_normaliser[0]!='')
                {
                    //si le caractère à normaliser est ';' (puisqu'il est échappé il faut le deséchapper) 
                    if($car_a_normaliser[0]=='\;'){$car_a_normaliser[0]=';';}
                    //on effectue la normalisation
                    $texte=str_replace($car_a_normaliser[0], $car_a_normaliser[1], $texte);
                }
            }
        }
	}
	return $texte;

}

function recombinaison($recombinaisons)
{
    $recombinaison=preg_split("/(?<!\\\);/", $recombinaisons); //on explose la chaîne à tous les ';' (sauf s'ils sont échappés)
        
    $combin=array();
    //pour chaque paire de recombinaison
    for($r=0; $r<count($recombinaison); $r++)
    {
        if($recombinaison[$r]!='')
        {
            //on recupère les caractères à normaliser (à gauche du :). On explose donc quand on a ':' sauf si échappé 
            $tok_a_recombiner=preg_split("/(?<!\\\):/", $recombinaison[$r]);      
            if(isset($tok_a_recombiner[0]) && isset($tok_a_recombiner[1]) && $tok_a_recombiner[0]!='' && $tok_a_recombiner[1]!='')
            { 
                //si le caractère a recombiner est ':' (puisqu'il est échappé il faut le "déséchapper") 
                if($tok_a_recombiner[0]=="\:"){$tok_a_recombiner[0]=":";}
                //on vérifie que ce qui est à droite de : est bien > ou <
                if($tok_a_recombiner[1]=="<" || $tok_a_recombiner[1]==">")
                {
                    $combin[$tok_a_recombiner[0]]=$tok_a_recombiner[1];
                }
            }
        }
    }
    return $combin;
}

//fonction chargée de changer la variable indiquant si un token est ouvert ou non
function changement_ouverture()
{
    if($GLOBALS['ouvert']==1)
    {
        $GLOBALS['ouvert']=0;
    }
    else 
    {
        $GLOBALS['ouvert']=1;
    }
}

function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}
?>


<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Chunker</title>
        
        <style>
            summary::-webkit-details-marker
            {
                display: none
            }

            summary:after 
            {
                display:inline-block;
                background: red; 
                border-radius: 5px; 
                content: "+"; 
                color: #fff; 
                float: left; 
                font-size: 1.5em; 
                font-weight: bold; 
                margin: 2px 2px 2px 2px; 
                padding: 0; 
                text-align: center; 
                width: 20px;
            }

            details[open] summary:after
            {
            	content: "-";
            }


            a.info
            {
                position: relative;
                color: black;
            }

            a.info span 
            {
                display: none; /* on masque l'infobulle */
            }


            a.info:hover span 
            {
                z-index:100;
                display: block; /* on affiche l'infobulle */
                position: absolute;

                white-space: nowrap; /* on change la valeur de la propriété white-space pour qu'il n'y ait pas de retour à la ligne non-désiré */

                top: 30px; /* on positionne l'infobulle */
                left: 20px;

                background-color: white;

                color: black;
                padding: 3px;

                border: 1px solid black;
                border-left: 3px solid black;
            }

            .erreur
            {
                color:white;
                background-color:red;
                border:1px dotted white;
                padding:10px;
                text-align:center;
            }
        </style>
    </head>
    <body>
        <h1 align=center>Chunker</h1>
        <p>Site optimisé pour Google Chrome !
            <details>
                <summary>Les classes d'éléments</summary>
                    <ul>
                        <li>Elles doivent être définies de la sorte : nom_de_la_classe := élément1 élément2 élément3 ;</li>
                        <li>Le nom de la classe peut être en plusieurs mots ou en un seul</li>
                        <li>Le nom de la classe et les éléments de celle-ci doivent être séparés par <strong>:=</strong></li>
                        <li>Les éléments de la classe doivent être séparés par des espaces</li>
                        <li>La fin de la déclaration d'une classe doit se terminer par <strong>;</strong></li>
                        <li>Il est possible de déclarer plusieurs classes sur une même ligne</li>
						<li>Pour faire apparaître le point-virgule en tant qu'élément d'une classe, il faut l'échapper au moyen d'un antislash '\'</li>
                        <li>Ecrivez les membres d'une classe en minuscule ; les tokens sont automatiquement mis en minuscule afin d'éviter de saisir toutes les variantes possibles au sein d'une classe : je JE Je tu Tu TU, etc.</li>
                    </ul>
            </details><br>
            <details>
                <summary>Les règles</summary>
                    <ul>
                        <li>Elles doivent être définies de la sorte : Condition1 + Condition2 = Action1 + Action2 ;</li>
                        <li>Les conditions et les actions doivent être séparées par <strong>=</strong></li>
                        <li>Les conditions doivent être séparées les unes des autres par un <strong>+</strong></li>
                        <li>Les actions doivent être séparées les unes des autres par un <strong>+</strong></li>
                        <li>Une condition peut être :<ul>
                            <li><strong>?</strong> : toujours vraie</li>
                            <li><strong>"ABCDE"</strong> : sous forme graphique (forme graphique entre guillemets). On regarde si le token étudié à la même graphie que la condition</li>
                            <li><strong>/"une expression régulière"/</strong> : (expression entre slashs) au format Perl</li>
                            <li><strong>le nom d'une classe</strong></li>
                            <br>
                            <li>Il est possible également de créer des conditions négatives, il suffit simplement de la faire précéder de '#'. Exemple : Condition1 + <strong>#Condition2</strong> = (Chunk) Action1 + Action 2 n'ouvrira un chunk de type "Chunk" que si Condition1 est vraie et que Condition2 est fausse.<br>
                            Ecrire '... + #"je" + ...' équivaut à écrire '... + ?#"je" + ...' (les deux syntaxes sont correctes). Il est donc possible d'écrire des choses du type '... + NomClasse#"élement1"#"/aient$/" + ...'</li><br><br>
                            </ul>
                        </li>
                        <li>Il est possible de déclarer plusieurs règles sur une même ligne</li>
                        <li>L'ouverture d'un chunk est déclenchée par les parenthèses, entre lesquelles se trouve le nom du chunk. Plusieurs chunks peuvent être ouverts dans une même suite d'actions : <strong>PPS + ? = (PPS) _ + (V) _;</strong></li>
                        <li>La fermeture d'un chunk est automatique. On peut forcer la fermeture en ajoutant un <strong>&</strong> à l'endroit où l'on souhaite fermer le chunk (en le liant aux autres éléments  avec des '+') : <strong>PCTNF = (PCTNF) _ + &;</strong></li>
                        <li>Le programme remplace automatiquement les ____ par le token correspondant à la condition située à la même position</li>
                        <li>Un chunk inconnu est indiqué de la manière suivante <?php echo '<sub><strong style="color:red;font-size:22px;">'.$labelChunkInconnu.'</strong></sub>'.$baliseO.'contenu'.$baliseF ?></li>
                    </ul>                
            </details><br>
            <details>
                <summary>Autre</summary>
                    <ul>
                        <li>Groupe : Renaud Mousnier-Lompré - William N. Havard</li>
                        <li>HTML : Thomas Lebarbé</li>
                        <li>CSS des balises détails :  <a href="http://html5doctor.com/the-details-and-summary-elements" target="_blank">http://html5doctor.com/the-details-and-summary-elements</a></li>
                    </ul>                
            </details>
           
        </p>
        <center>
            <?php
            if(isset($_POST['chunker']))
            {	
                //on avertit l'utilisateur des éventuelles erreurs
                foreach($erreur as $aff_erreur)
                {
                        echo '<div class="erreur">'.$aff_erreur.'</div><br><br>';
                }
            }?>
            <form method="post" action="chunks.php">
                <table width="100%" height="50%" border=1>
                    <tr>
                        <th colspan=4 align=center>Chunker à base de règles<br>William N. Havard - Renaud Mousnier Lompré</th>
                    </tr>
                        <tr>
                            <td colspan=4>
                                <details>
                                    <summary>Paramètres</summary>
                                        <ul>
                                            <li><strong>Opérations sur le texte d'entrée</strong></li>
                                                    La normalisation vous permet de remplacer certains caractères du texte par d'autres afin d'éviter à avoir à dupliquer des règles pour un seul caractère de différence.<br>
                                                    Nous préconisons par exemple de normaliser toutes les sortes d'apostrophe (ʼ’) en apostrophe droite ('). Pour ce faire, il suffit d'écrire : "caractère à normaliser => caractère de normalisation ;"
                                                    <br><input type="text" name="normalisation" value="<?=htmlspecialchars($norm, ENT_QUOTES, "UTF-8")?>">
                                                    <br><br>
                                            <li><strong>Opération sur les tokens</strong></li>
                                                    Vous pouvez indiquer ici les caractères qui seront considérés comme séparateurs de tokens. À l'exception des espaces, aucun des caractères séparateurs ne sera supprimé, ils formeront chacun un token.
                                                    <br><input type="text" name="carTok" value="<?=htmlspecialchars($carTok, ENT_QUOTES, "UTF-8")?>">
                                                    <br><br>
                                                    Il est possible d'indiquer ci-dessous des règles de recombinaison de tokens. Nos recommandons par exemple de raccrocher les apostrophes au token qui les précède.<br>Pour ce faire, il suffit d'écrire : "token à déplacer : < (raccrocher au token à précédent) OU > (raccrocher au token suivant);"
                                                    <br><input type="text" name="recombi" value="<?=htmlspecialchars($recombinaisons, ENT_QUOTES, "UTF-8")?>">
                                                    <br><br>

                                            <li><strong>Opération sur les chunks</strong></li>
                                                    Caractères matérialisant l'ouverture et la fermeture d'un chunk : 
                                                    <input type="text" name="ouvreChunk" value="<?=htmlspecialchars($baliseO, ENT_QUOTES, "UTF-8")?>" style="text-align:right;" size="1">chunk
                                                    <input type="text" name="fermeChunk" value="<?=htmlspecialchars($baliseF, ENT_QUOTES, "UTF-8")?>" style="text-align:left;" size="1"><br>
                                                    <input type=checkbox name="apresChunk" <?=$apresChunk!=''?'CHECKED':''?>  size="1"> <label>Retour à la ligne après la fermeture d'un chunk</label><br><br>
                                                    <label>Label d'un chunk inconnu</label><input type="text" name="labelChunkInconnu" value="<?=htmlspecialchars($labelChunkInconnu, ENT_QUOTES, "UTF-8")?>">
                                        </ul>
                                </details>
                            </td>
                        </tr>

                    <tr>
                        <th align=center>Classes et Règles</th>
                        <th align=center>Texte</th>
                        <td rowspan=2 style="width:50px;"><input type=submit value="Chunke-moi ça ⇰" style="width:auto; height:100%;"name="chunker"></td>
                        <th align=center>Résultat</th>
                    </tr>
                    
                    <tr>
                        <td>
                            <textarea style="width:100%; height:500px;" name="regles"><?=$valRegles?></textarea>
                        </td>
                        <td>
                            <textarea style="width:100%; height:100%;" name="texte"><?=$valTexte?></textarea>
                        </td>
                        <td style="width:30%;">
                            <div style="height:100%;overflow:scroll;"><?=$sortie?></div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan=4>
                            <input type=checkbox name="num" <?=isset($_POST['num'])?'CHECKED':''?>>afficher les numéros des règles appliquées + nature des tokens dans le texte chunké
                            <br><input type=checkbox name="graph" <?=isset($_POST['graph'])?'CHECKED':''?>>afficher un graphique d'utilisation des règles
                            <br><input type=checkbox name="inconnu" <?=isset($_POST['inconnu'])?'CHECKED':''?>>inclure les chunks inconnus dans les statistiques
                        </td>
                    </tr>
                </table>
            </form>
        </center>

    <?php
    //si on a bien envoyé un check à chunker, on affiche les statistiques
    if(isset($_POST['chunker']))
    {	
		echo '<center>'.count($tokens).' tokens analysés en '.round($time, 3).' secondes.<br>'.$nbChunk.' chunks créés</center><br><br>';
        
        //calcul des fréquances d'utilisation des règles
        if(isset($_POST['graph']))
        {
            $nbReglesUtilisees=0;
            
            //si l'utilisateur souhaite que l'ouverture d'un chunk inconnu soit considérée comme une règle
            if(isset($_POST['inconnu']))
            {
                $nbReglesUtilisees++;
                $regles[0][]=$labelChunkInconnu;
                $regles[1][]=$labelChunkInconnu;
                $regles[2][]=$chunkInconnus;
            }
            
            //tableau qui nous servira à stocker les règles pour faire un tri dessus ensuite
            $ordreRegle=array();
            for($i=0; $i<count($regles[2]); $i++)
            {
                $nbReglesUtilisees+=$regles[2][$i];
                $ordreRegle[$i]=$regles[2][$i];
            }
            //on range par ordre décroissant
            arsort($ordreRegle);

            echo '<center>
                    <table>
                    <tr>
                       <th colspan=3 align=center>Statistiques d\'application</th>
                    </tr>';
            foreach($ordreRegle as $cle=>$valeur)
            {
                if($regles[0][$cle]=="INC")
                {
                    $spanD='<span style="color:red;"><strong>';
                    $spanF='</strong></span>';
                }
                else
                {
                    $spanD='';
                    $spanF='';
                }
				
               echo'<tr>
                       <td style="padding-right:20px;" align=right>#'.$cle.'</td>
                       <td style="padding-right:20px;" align=center>'.$spanD.$regles[0][$cle].' = '.$regles[1][$cle].$spanF.'</td>
                       <td style="padding-right:20px;" align=right>'.$valeur.' fois</td>
                       <td style="padding-right:20px;" align=right>'.round(($regles[2][$cle]/$nbReglesUtilisees*100), 2).' %</td>
                       <td>
                           <progress style="height:25px;width:200px;"  max="100" value="'.round(($regles[2][$cle]/$nbReglesUtilisees*100), 2).'">
                       </td>
                    </tr>
                 ';            
            }
            echo '</table>'
            . '</center>';
        }

        //permet d'afficher les tokens, catégories et règles si besoin
        echo "<details>\n\t<summary>Tokens</summary><br>\n";
            foreach($tokens as $cleT=>$valT)
            {
                echo "[".$cleT."] => ".$valT."<br>\n";
            }
        echo "</details><br>\n";

        echo "<details>\n\t<summary>Catégories</summary><br\n>";
            foreach($cat as $cleC=>$valC)
            {
                echo "[".$cleC."] => <br>";
                foreach($valC as $cleValC=>$aff_ValC)
                {	
                    echo "<span style=\"margin-left:25px;\">[".$cleValC."] => ".$aff_ValC."</span><br>\n";
                }
            }
        echo "</details><br>\n";

        echo "<details>\n\t<summary>Règles</summary><br>";
            foreach($regles as $cleR=>$valR)
            {
                echo "[".$cleR."] => <br>\n";
                foreach($valR as $cleValR=>$aff_ValR)
                {	
                    echo "\t\t<span style=\"margin-left:25px;\">[".$cleValR."] => ".$aff_ValR."</span><br>\n";
                }
            }
        echo "</details>";
    }
    ?>
    </body>
</html>    