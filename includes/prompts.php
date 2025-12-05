<?php
/**
 * Archivo: prompts.php
 * Ubicación: includes/prompts.php
 * Descripción: Función para obtener la plantilla (prompt) de redacción de artículos 
 * con enfoque en originalidad y evasión de copyright.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita el acceso directo.
}

/**
 * Obtiene la plantilla de prompt según el idioma seleccionado.
 *
 * @param string $language Código de idioma (ej. 'es', 'en', 'de', etc.).
 * @param string $titulo   Título original del artículo.
 * @param string $contenido Contenido original del artículo.
 * @param string $category_list_string Una cadena con la lista de categorías de WP.
 * @return string Prompt a enviar a la API de OpenAI.
 */
function dsrw_get_prompt_template( $language, $titulo, $contenido, $category_list_string ) {

    // 1. Revisar si hay un prompt personalizado
    $custom_prompt = get_option('dsrw_custom_prompt', '');
    $custom_prompt = trim($custom_prompt);

    if ( ! empty($custom_prompt) ) {
        return str_replace(
            array('{$titulo}', '{$contenido}', '{$categorias}'),
            array($titulo, $contenido, $category_list_string),
            $custom_prompt
        );
    }

    // 2. Cargar los prompts "Redacción Original"
    $prompts = dsrw_get_default_prompts_list($category_list_string);

    // 3. Seleccionar el idioma (o fallback a español)
    $chosen_template = isset( $prompts[ $language ] ) ? $prompts[ $language ] : $prompts['es'];

    // 4. Inyectar título y contenido
    $final_prompt = str_replace(
        array('{$titulo}', '{$contenido}'),
        array($titulo, $contenido),
        $chosen_template
    );
    
    return $final_prompt;
}

/**
 * Devuelve el array con prompts diseñados para crear contenido ORIGINAL basado en hechos,
 * evitando el simple parafraseo para reducir problemas de copyright.
 * * @param string $cats Lista de categorías para inyectar en las instrucciones.
 * @return array Array asociativo ['idioma' => 'texto_prompt'].
 */
function dsrw_get_default_prompts_list( $cats ) {
    return array(
        'es' => <<<EOT
Actúa como un Redactor Jefe de un medio digital de alto prestigio. Tu misión es crear una noticia NUEVA y ORIGINAL basada en los hechos del texto proporcionado a continuación.
NO REESCRIBAS ni parafrasees el texto original. Úsalo solo como fuente de datos (investigación) y escribe tu propia historia desde cero.

Lista de categorías disponibles: [{$cats}]

Devuélvelo exclusivamente en este objeto JSON:
{
    "title": "string (Un titular totalmente nuevo, atractivo y con 'gancho', muy diferente al original)",
    "content": "string (Tu artículo original en HTML. Usa h2, strong, p. Mínimo 400 palabras)",
    "slug": "string",
    "category": "string", // Elige la más específica de la lista
    "excerpt": "string (Resumen intrigante)",
    "tags": "array[string]"
}

**Reglas de Oro para la Originalidad:**
1. **CAMBIO DE ESTRUCTURA:** No sigas el orden de párrafos del original. Si el original empieza por el final, tú empieza por el contexto. Cambia radicalmente el flujo de la información.
2. **NO COPIES:** Lee el texto, extrae los datos factuales (qué, quién, cuándo, dónde) y redáctalos con tus propias palabras y estilo.
3. **TONO PROPIO:** Usa un tono profesional, objetivo pero ameno.
4. **CERO PLAGIO:** Está prohibido copiar frases enteras o mantener la misma sintaxis que la fuente.
5. **Formato:** Usa <h2> para separar secciones nuevas que crees tú. Usa <strong> para destacar datos clave.
6. **Mayúsculas:** Respeta escrupulosamente las mayúsculas en nombres propios e inicios de frase. No capitalices: No Hagas Esto Con Las Mayusculas De Los Titulos
7. **Deportes:** Si hablas de equipos, usa artículos (El Real Madrid...).

Fuente original (solo para extraer datos):
Título: "{\$titulo}"
Datos: "{\$contenido}"
EOT,

        'en' => <<<EOT
Act as an Editor-in-Chief of a prestigious digital outlet. Your mission is to create a NEW and ORIGINAL news piece based on the facts from the text provided below.
DO NOT REWRITE or paraphrase the original text. Use it only as a data source (research) and write your own story from scratch.

Available categories list: [{$cats}]

Return exclusively this JSON object:
{
    "title": "string (A completely new, catchy headline, very different from the original)",
    "content": "string (Your original article in HTML. Use h2, strong, p. Min 400 words)",
    "slug": "string",
    "category": "string", // Choose the most specific from the list
    "excerpt": "string (Intriguing summary)",
    "tags": "array[string]"
}

**Golden Rules for Originality:**
1. **STRUCTURAL CHANGE:** Do not follow the paragraph order of the original. Radically change the flow of information.
2. **SYNTHESIS, NOT COPY:** Read the text, extract factual data (who, what, when, where), and write them in your own words and style.
3. **OWN VOICE:** Use a professional, objective, yet engaging tone. Explain "why" this news matters.
4. **ZERO PLAGIARISM:** Copying entire sentences or keeping the same syntax is forbidden.
5. **Format:** Use <h2> for new sections you create. Use <strong> for key facts.
6. **Capitalization:** Strictly respect capitalization for proper nouns and sentence starts.
7. **Sports:** Keep articles before team names (e.g., The Real Madrid...).

Original source (for data extraction only):
Title: "{\$titulo}"
Data: "{\$contenido}"
EOT,

        'de' => <<<EOT
Handle als Chefredakteur eines angesehenen digitalen Mediums. Deine Aufgabe ist es, einen NEUEN und ORIGINALEN Nachrichtenartikel basierend auf den Fakten des untenstehenden Textes zu erstellen.
SCHREIBE NICHT EINFACH UM (kein Paraphrasieren). Nutze den Text nur als Datenquelle und schreibe deine eigene Geschichte von Grund auf neu.

Verfügbare Kategorien: [{$cats}]

Gib ausschließlich dieses JSON-Objekt zurück:
{
    "title": "string (Ein völlig neuer, attraktiver Titel)",
    "content": "string (Dein Originalartikel in HTML. Nutze h2, strong, p. Min. 400 Wörter)",
    "slug": "string",
    "category": "string", // Wähle die spezifischste aus der Liste
    "excerpt": "string (Zusammenfassung)",
    "tags": "array[string]"
}

**Goldene Regeln für Originalität:**
1. **STRUKTURÄNDERUNG:** Folge nicht der Absatzreihenfolge des Originals. Ändere den Informationsfluss radikal.
2. **SYNTHESE STATT KOPIE:** Extrahiere die Fakten und schreibe sie mit deinen eigenen Worten.
3. **EIGENER TON:** Sei professionell und objektiv.
4. **KEIN PLAGIAT:** Das Kopieren ganzer Sätze ist verboten.
5. **Formatierung:** Nutze <h2> und <strong>.
6. **Großschreibung:** Achte penibel auf korrekte Groß- und Kleinschreibung.

Originalquelle (nur zur Datenextraktion):
Titel: "{\$titulo}"
Daten: "{\$contenido}"
EOT,

        'fr' => <<<EOT
Agissez en tant que rédacteur en chef d'un média numérique prestigieux. Votre mission est de créer un article d'actualité NOUVEAU et ORIGINAL basé sur les faits du texte ci-dessous.
NE RÉÉCRIVEZ PAS et ne paraphrasez pas le texte original. Utilisez-le uniquement comme source de données et écrivez votre propre histoire à partir de zéro.

Liste des catégories disponibles : [{$cats}]

Renvoyez exclusivement cet objet JSON :
{
    "title": "string (Un titre totalement nouveau et accrocheur)",
    "content": "string (Votre article original en HTML. Utilisez h2, strong, p. Min 400 mots)",
    "slug": "string",
    "category": "string", // Choisissez la plus spécifique
    "excerpt": "string (Résumé intrigant)",
    "tags": "array[string]"
}

**Règles d'or pour l'originalité :**
1. **CHANGEMENT DE STRUCTURE :** Ne suivez pas l'ordre des paragraphes de l'original. Changez radicalement le flux d'informations.
2. **SYNTHÈSE, PAS COPIE :** Extrayez les faits et rédigez-les avec vos propres mots.
3. **TON PROPRE :** Utilisez un ton professionnel et engageant.
4. **ZERO PLAGIAT :** Il est interdit de copier des phrases entières.
5. **Format :** Utilisez <h2> et <strong>.
6. **Majuscules :** Respectez scrupuleusement les majuscules.

Source originale (pour extraction de données uniquement) :
Titre : "{\$titulo}"
Données : "{\$contenido}"
EOT,

        // Se mantienen los demás idiomas con la misma lógica simplificada para ahorrar espacio aquí, 
        // pero idealmente deberían seguir el mismo patrón de "NUEVO y ORIGINAL".
        'no' => <<<EOT
Opptre som sjefredaktør. Lag en NY og ORIGINAL nyhetsartikkel basert på faktaene nedenfor.
IKKE SKRIV OM eller omskriv originalteksten. Bruk den kun som kilde og skriv din egen historie fra bunnen av.

Tilgjengelige kategorier: [{$cats}]

Returner kun dette JSON-objektet:
{
    "title": "string (En helt ny, fengende tittel)",
    "content": "string (Din originale artikkel i HTML. Bruk h2, strong, p)",
    "slug": "string",
    "category": "string", 
    "excerpt": "string",
    "tags": "array[string]"
}

**Instruksjoner:**
1. Endre strukturen på informasjonen radikalt.
2. Trekk ut fakta og skriv med egne ord.
3. Ingen plagiering av setninger.
4. Bruk riktig stor bokstav.

Originalkilde:
Tittel: "{\$titulo}"
Data: "{\$contenido}"
EOT,

        'is' => <<<EOT
Komdu fram sem ritstjóri. Skrifaðu NÝJA og FRUMLEGA frétt byggða á staðreyndum hér að neðan.
EKKI ENDURSKRIFA upprunalega textann. Notaðu hann bara sem heimild og skrifaðu þína eigin sögu frá grunni.

Tiltækir flokkar: [{$cats}]

Skilaðu eingöngu þessum JSON hlut:
{
    "title": "string (Alveg nýr og grípandi titill)",
    "content": "string (Þín frumlega grein í HTML. Notaðu h2, strong, p)",
    "slug": "string",
    "category": "string",
    "excerpt": "string",
    "tags": "array[string]"
}

**Leiðbeiningar:**
1. Breyttu uppbyggingu upplýsinganna algjörlega.
2. Dragðu út staðreyndir og skrifaðu með eigin orðum.
3. Enginn ritstuldur á setningum.
4. Notaðu rétta hástafi.

Upprunaleg heimild:
Titill: "{\$titulo}"
Gögn: "{\$contenido}"
EOT,

        'sv' => <<<EOT
Agera som chefredaktör. Skapa en NY och ORIGINELL nyhetsartikel baserad på fakta nedan.
SKRIV INTE OM originaltexten rakt av. Använd den bara som datakälla och skriv din egen historia från grunden.

Tillgängliga kategorier: [{$cats}]

Returnera endast detta JSON-objekt:
{
    "title": "string (En helt ny, fångande rubrik)",
    "content": "string (Din originalartikel i HTML. Använd h2, strong, p)",
    "slug": "string",
    "category": "string",
    "excerpt": "string",
    "tags": "array[string]"
}

**Instruktioner:**
1. Ändra informationens struktur radikalt.
2. Extrahera fakta och skriv med egna ord.
3. Ingen plagiering av meningar.
4. Använd korrekta versaler.

Originalkälla:
Titel: "{\$titulo}"
Data: "{\$contenido}"
EOT
    );
}