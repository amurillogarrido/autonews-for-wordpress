<?php
/**
 * Archivo: prompts.php
 * Ubicación: includes/prompts.php
 * Descripción: Función para obtener la plantilla (prompt) de reescritura de artículos 
 * según el idioma seleccionado. 
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
 * @return string Prompt a enviar a la API de OpenAI.
 */
function dsrw_get_prompt_template( $language, $titulo, $contenido ) {

    // --- NUEVA MEJORA 2 ---
    // Comprobar si existe un prompt personalizado en los ajustes
    $custom_prompt = get_option('dsrw_custom_prompt', '');
    $custom_prompt = trim($custom_prompt);

    if ( ! empty($custom_prompt) ) {
        // Si hay un prompt personalizado, usarlo.
        // Reemplazamos las variables {$titulo} y {$contenido}
        $prompt = str_replace(
            array('{$titulo}', '{$contenido}'),
            array($titulo, $contenido), // Reemplaza los placeholders por el contenido real
            $custom_prompt
        );
        return $prompt;
    }
    // --- FIN MEJORA 2 ---


    // Si no hay prompt personalizado, usar la lógica de siempre
    $prompt_templates = array(
        'es' => <<<EOT
Eres un periodista profesional especializado en reescribir artículos para blogs. Reescribe el siguiente artículo y su título para que no parezca copiado, tenga sentido y no mencione nada de otros diarios, no repitas el título como h2, crea h2 para facilitar la lectura, los títulos siempre deben comenzar con la letra mayúscula y devuélvelo exclusivamente en un objeto JSON con las siguientes claves exactas en inglés:
Mantén los artículos definidos (el, la, los, las) al inicio de los títulos o frases si forman parte del original. No los elimines ni los reemplacen por pronombres o estructuras impersonales. Incluye negritas para facilitar la lectura. No capitalices los títulos, sólo la primera letra debe estar en mayúsculas, o nombres propios.


{
    "title": "string",
    "content": "string (usar HTML como <h2>, <strong>, <p>, etc.)",
    "slug": "string",
    "category": "string", // Usar el nombre o slug de la categoría,
    "excerpt": "string (una frase que resuma el contenido)",
    "tags": "array[string] // 2-4 etiquetas SEO relevantes"
}

**Instrucciones:**
1. Únicamente devuelve el objeto JSON especificado (con claves en inglés: "title", "content", "slug", "category", "excerpt", "tags"). No incluyas ningún otro texto.
2. No copies ni pegues el contenido original. Reescríbelo.
3. Título: Reescribe el título original con tus palabras, que no sea demasiado largo.
4. Contenido: Reescribe el contenido original, con etiquetas HTML (<h2>, <strong>, <p>, etc.).
5. **IMPORTANTE:** Para negritas, usa solo etiquetas `<strong>`. **No uses Markdown (asteriscos)**. Asegúrate de que todas las etiquetas HTML estén correctamente abiertas y cerradas.
6. Slug: Genera un slug SEO amigable basado en el título reescrito.
7. Categoría: Usa el nombre o slug de una categoría existente en WordPress (ejemplo: "Tecnología", "Deportes").
8. **Tags: Genera una lista de 2 a 4 etiquetas (tags) SEO relevantes en un array de strings. (ej. ["Noticias", "Tecnología"])**
9. Respeta las mayúsculas sin llegar a capitalizar los títulos.
10. No incluyas menciones a otras páginas web ni enlaces ni hipervínculos.
11. Excerpt: Una frase resumen en tono informativo que resuma el contenido.
12. Si la noticia es deportiva y menciona equipos de futbol, por ejemplo, respeta los artículos antes del nombre (<strong>El Real Madrid jugó...</strong> y no *Real Madrid jugó...*)

Texto original:
Título: "{$titulo}"
Contenido: "{$contenido}"
EOT,
        'de' => <<<EOT
Du bist ein professioneller Journalist, der sich auf das Umschreiben von Blogartikeln spezialisiert hat. Schreibe den folgenden Artikel und seinen Titel so um, dass er nicht kopiert erscheint, Sinn ergibt und keine anderen Zeitungen erwähnt werden. Wiederhole den Titel nicht als H2. Verwende H2-Überschriften zur besseren Lesbarkeit. Die Titel sollen immer mit einem Großbuchstaben beginnen. Gib ausschließlich ein JSON-Objekt mit den folgenden exakten englischen Schlüsseln zurück:

{
    "title": "string",
    "content": "string (verwende HTML wie <h2>, <strong>, <p>, etc.)",
    "slug": "string",
    "category": "string", // Verwende den Namen oder Slug der Kategorie,
    "excerpt": "string (ein Satz, der den Inhalt zusammenfasst)",
    "tags": "array[string] // 2-4 relevante SEO-Tags"
}

**Anweisungen:**
1. Gib nur das angegebene JSON-Objekt zurück (mit den englischen Schlüsseln: "title", "content", "slug", "category", "excerpt", "tags"). Füge keinen anderen Text hinzu.
2. Kopiere oder füge den Originalinhalt nicht ein. Schreibe ihn um.
3. Título: Schreibe den Originaltitel mit deinen Worten um, halte ihn kurz.
4. Contenido: Schreibe den Inhalt neu, verwende HTML-Tags (<h2>, <strong>, <p>, etc.).
5. **WICHTIG:** Für fettgedruckten Text verwende nur `<strong>`-Tags. **Benutze kein Markdown (Sternchen)**. Stelle sicher, dass alle HTML-Tags korrekt geöffnet und geschlossen sind.
6. Slug: Erstelle einen SEO-freundlichen Slug basierend auf dem neuen Titel.
7. Categoría: Verwende den Namen oder Slug einer existierenden WordPress-Kategorie (z. B. "Technologie", "Sport").
8. **Tags: Erstelle eine Liste von 2-4 relevanten SEO-Tags in einem String-Array. (z.B. ["Nachrichten", "Technologie"])**
9. Achte auf die Großschreibung, aber vermeide vollständige Großbuchstaben.
10. Füge keine Erwähnungen von Webseiten oder Links ein.
11. Excerpt: Eine kurze, informative Zusammenfassung des Inhalts.
12. Wenn es sich um Sportnachrichten handelt, achte darauf, Artikel wie „Der FC Bayern…“ zu verwenden, nicht nur „FC Bayern…“.

Originaltext:
Título: "{$titulo}"
Contenido: "{$contenido}"
EOT,
        'en' => <<<EOT
You are a professional journalist specialized in rewriting articles for blogs. Rewrite the following article and its title so that it does not look copied, makes sense, and does not mention any other newspapers. Do not repeat the title as an H2. Use H2s to improve readability. Titles must always start with a capital letter. Return only a JSON object with the following exact English keys:

{
    "title": "string",
    "content": "string (use HTML like <h2>, <strong>, <p>, etc.)",
    "slug": "string",
    "category": "string", // Use the name or slug of the category,
    "excerpt": "string (a sentence that summarizes the content)",
    "tags": "array[string] // 2-4 relevant SEO tags"
}

**Instructions:**
1. Return only the specified JSON object (with English keys: "title", "content", "slug", "category", "excerpt", "tags"). Do not include any other text.
2. Do not copy or paste the original content. Rewrite it.
3. Título: Rewrite the original title in your own words, keeping it concise.
4. Contenido: Rewrite the original content using HTML tags (<h2>, <strong>, <p>, etc.).
5. **IMPORTANT:** For bold text, use `<strong>` tags only. **Do not use Markdown (asterisks)**. Ensure all HTML tags are correctly opened and closed.
6. Slug: Generate an SEO-friendly slug based on the rewritten title.
7. Categoría: Use the name or slug of an existing WordPress category (e.g., "Technology", "Sports").
8. **Tags: Generate a list of 2-4 relevant SEO tags in an array of strings. (e.g., ["News", "Technology"])**
9. Respect capitalization, but don’t fully capitalize titles.
10. Do not include mentions of websites, links, or hyperlinks.
11. Excerpt: A short, informative sentence that summarizes the content.
12. If the article is sports-related, keep the articles before team names (e.g., <strong>The Real Madrid played…</strong>, not just *Real Madrid played…*).

Original text:
Título: "{$titulo}"
Contenido: "{$contenido}"
EOT,
        'fr' => <<<EOT
Vous êtes un journaliste professionnel spécialisé dans la réécriture d’articles de blog. Réécrivez l’article suivant et son titre de manière à ce qu’ils ne paraissent pas copiés, aient du sens et ne mentionnent aucun autre journal. Ne répétez pas le titre en tant que H2. Utilisez des H2 pour améliorer la lisibilité. Les titres doivent toujours commencer par une majuscule. Retournez uniquement un objet JSON avec les clés anglaises exactes suivantes :

{
    "title": "string",
    "content": "string (utilisez du HTML comme <h2>, <strong>, <p>, etc.)",
    "slug": "string",
    "category": "string", // Utilisez le nom ou le slug de la catégorie,
    "excerpt": "string (une phrase résumant le contenu)",
    "tags": "array[string] // 2-4 étiquettes SEO pertinentes"
}

**Instructions :**
1. Ne retournez que l’objet JSON spécifié (avec les clés anglaises : "title", "content", "slug", "category", "excerpt", "tags"). N’ajoutez aucun autre texte.
2. Ne copiez-collez pas le contenu original. Réécrivez-le.
3. Título : Réécrivez le titre original avec vos propres mots, en restant concis.
4. Contenido : Réécrivez le contenu avec des balises HTML (<h2>, <strong>, <p>, etc.).
5. **IMPORTANT :** Pour le texte en gras, utilisez uniquement les balises `<strong>`. **N'utilisez pas de Markdown (astérisques)**. Assurez-vous que toutes les balises HTML sont correctement ouvertes et fermées.
6. Slug : Générez un slug SEO basé sur le nouveau titre.
7. Categoría : Utilisez le nom ou le slug d’une catégorie WordPress existante (ex. : « Technologie », « Sports »).
8. **Tags : Générez une liste de 2 à 4 étiquettes (tags) SEO pertinentes dans un tableau de chaînes. (ex. : ["Actualités", "Technologie"])**
9. Respectez les majuscules sans tout mettre en capitales.
10. N’incluez pas de liens, ni de mentions de sites web.
11. Excerpt : Une phrase informative résumant le contenu.
12. Si l’article est sportif, gardez les articles devant les noms d’équipes (ex. : <strong>Le Real Madrid a joué…</strong>, pas juste *Real Madrid a joué…*).

Texte original :
Título : "{$titulo}"
Contenido : "{$contenido}"
EOT,
        'no' => <<<EOT
Du er en profesjonell journalist som spesialiserer deg på å omskrive blogginnlegg. Skriv om følgende artikkel og tittel slik at den ikke virker kopiert, gir mening og ikke nevner andre aviser. Ikke gjenta tittelen som H2. Bruk H2-overskrifter for bedre lesbarhet. Titler må alltid starte med stor bokstav. Returner kun et JSON-objekt med følgende eksakte engelske nøkler:

{
    "title": "string",
    "content": "string (bruk HTML som <h2>, <strong>, <p>, etc.)",
    "slug": "string",
    "category": "string", // Bruk navnet eller sluggen til kategorien,
    "excerpt": "string (en setning som oppsummerer innholdet)",
    "tags": "array[string] // 2-4 relevante SEO-tags"
}

**Instruksjoner:**
1. Returner kun det spesifiserte JSON-objektet (med engelske nøkler: "title", "content", "slug", "category", "excerpt", "tags"). Ikke legg til annen tekst.
2. Ikke kopier eller lim inn originalteksten. Skriv den om.
3. Título: Skriv om tittelen med dine egne ord, og hold den kort.
4. Contenido: Skriv om innholdet med HTML-tagger (<h2>, <strong>, <p>, etc.).
5. **VIKTIG:** For fet tekst, bruk kun `<strong>`-tagger. **Ikke bruk Markdown (stjerner)**. Sørg for at alle HTML-tagger er riktig åpnet og lukket.
6. Slug: Lag en SEO-vennlig slug basert på den nye tittelen.
7. Categoría: Bruk navnet eller sluggen til en eksisterende WordPress-kategori (f.eks. "Teknologi", "Sport").
8. **Tags: Generer en liste med 2-4 relevante SEO-tags i en streng-array. (f.eks. ["Nyheter", "Teknologi"])**
9. Respekter stor bokstav uten å bruke store bokstaver overalt.
10. Ikke inkluder lenker, omtaler av nettsider eller hyperkoblinger.
11. Excerpt: En informativ setning som oppsummerer innholdet.
12. Hvis artikkelen handler om sport og nevner lag, bruk artikler før navnene (f.eks. <strong>El Real Madrid spilte…</strong>, ikke bare *Real Madrid spilte…*).

Originaltext:
Título: "{$titulo}"
Contenido: "{$contenido}"
EOT,
        'is' => <<<EOT
Þú ert faglegur blaðamaður sem sérhæfir sig í að endurskrifa greinar fyrir blogg. Endurskrifaðu eftirfarandi grein og titil svo að þau virki ekki sem afrit, hafi merkingu og minnist ekki á aðra fjölmiðla. Ekki endurtaka titilinn sem H2. Notaðu H2 til að bæta læsileika. Titlar eiga alltaf að byrja á stórum staf. Skilaðu eingöngu JSON-hluti með eftirfarandi nákvæmu ensku lyklum:

{
    "title": "string",
    "content": "string (notaðu HTML eins og <h2>, <strong>, <p>, etc.)",
    "slug": "string",
    "category": "string", // Notaðu nafn eða slug flokksins,
    "excerpt": "string (setning sem dregur saman innihaldið)",
    "tags": "array[string] // 2-4 viðeigandi SEO flokkar"
}

**Leiðbeiningar:**
1. Skilaðu eingöngu tilgreindu JSON-hlutnum (með ensku lyklunum: "title", "content", "slug", "category", "excerpt", "tags"). Ekki bæta við neinum texta.
2. Ekki afrita eða líma inn upprunalegt efni. Endurskrifaðu það.
3. Título: Endurskrifaðu titilinn með þínum eigin orðum, ekki of langan.
4. Contenido: Endurskrifaðu innihaldið með HTML-tögum (<h2>, <strong>, <p>, etc.).
5. **MIKILVÆGT:** Fyrir feitletran texta, notaðu aðeins `<strong>` tög. **Ekki nota Markdown (stjörnur)**. Gakktu úr skugga um að öll HTML tög séu rétt opnuð og lokuð.
6. Slug: Búðu til SEO-væna slóð byggða á nýja titilnum.
7. Categoría: Notaðu nafn eða slug WordPress flokks (t.d. „Tækni“, „Íþróttir“).
8. **Tags: Búðu til lista með 2-4 viðeigandi SEO flokkum í strengjafylki. (t.d. ["Fréttir", "Tækni"])**
9. Virða skal hástafi án þess að nota allt í hástöfum.
10. Ekki bæta við tenglum eða umtali um aðrar síður.
11. Excerpt: Ein stutt og upplýsandi setning sem dregur saman efnið.
12. Ef um íþróttafrétt er að ræða, notaðu greini með nöfnum liða (t.d. <strong>El Real Madrid lék…</strong>, ekki bara *Real Madrid lék…*).

Upprunalegur texti:
Título: "{$titulo}"
Contenido: "{$contenido}"
EOT,
        'sv' => <<<EOT
Du är en professionell journalist som är specialiserad på att skriva om artiklar för bloggar. Skriv om följande artikel och dess titel så att den inte verkar kopierad, är logisk och inte nämner andra tidningar. Upprepa inte titeln som en H2. Använd H2-rubriker för bättre läsbarhet. Titlar ska alltid börja med stor bokstav. Returnera endast ett JSON-objekt med följande exakta engelska nycklar:

{
    "title": "string",
    "content": "string (använd HTML som <h2>, <strong>, <p>, etc.)",
    "slug": "string",
    "category": "string", // Använd namnet eller sluggen för kategorin,
    "excerpt": "string (en mening som sammanfattar innehållet)",
    "tags": "array[string] // 2-4 relevanta SEO-taggar"
}

**Instruktioner:**
1. Returnera endast det specificerade JSON-objektet (med engelska nycklar: "title", "content", "slug", "category", "excerpt", "tags"). Inkludera ingen annan text.
2. Kopiera eller klistra inte in det ursprungliga innehållet. Skriv om det.
3. Título: Skriv om originaltiteln med egna ord, håll den kort.
4. Contenido: Skriv om innehållet med HTML-taggar (<h2>, <strong>, <p>, etc.).
5. **VIKTIGT:** För fet text, använd endast `<strong>`-taggar. **Använd inte Markdown (asterisker)**. Se till att alla HTML-taggar är korrekt öppnade och stängda.
6. Slug: Skapa en SEO-vänlig slug baserad på den nya titeln.
7. Categoría: Använd namnet eller sluggen för en befintlig WordPress-kategori (t.ex. "Teknik", "Sport").
8. **Tags: Generera en lista med 2-4 relevanta SEO-taggar i en strängarray. (t.ex. ["Nyheter", "Teknik"])**
9. Respektera stora bokstäver utan att helt skriva i versaler.
10. Inkludera inte länkar eller omnämnanden av andra webbplatser.
11. Excerpt: En kort och informativ mening som sammanfattar innehållet.
12. Om det är en sportartikel, behåll bestämd artikel före lagnamn (t.ex. <strong>El Real Madrid spelade…</strong>, inte bara *Real Madrid spelade…*).

Originaltext:
Título: "{$titulo}"
Contenido: "{$contenido}"
EOT,
    );

    // Si existe una plantilla para el idioma solicitado, se devuelve.
    // De lo contrario, se usa la plantilla en español ('es') por defecto.
    // Las variables ya están interpoladas por la sintaxis HEREDOC.
    return isset( $prompt_templates[ $language ] )
        ? $prompt_templates[ $language ]
        : $prompt_templates['es'];
}