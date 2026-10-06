#!/usr/bin/env python3
"""Regenerates the SEO / GEO / AEO head block (title, meta, Open Graph, Twitter, JSON-LD)
on the five static pages. Run from the repo root:  python3 tools/build_seo.py
Edit the PAGES dict / PERSON dict below, run, commit."""
import json, re, html

SITE = "https://didiwalsonjack.com"
NAME = "Mrs. Didi Esther Walson-Jack, OON, mni"
LOGO = "https://res.cloudinary.com/pwkdspnn/image/upload/f_auto,q_auto,w_512/v1791282316/favicon-512.png"
PHOTO = "https://res.cloudinary.com/pwkdspnn/image/upload/f_auto,q_auto:good,w_1000/v1787818879/IMG_1929_h6dhus.webp"
CAREER_IMG = "https://res.cloudinary.com/pwkdspnn/image/upload/f_auto,q_auto:good,w_1000/v1787814192/Gemini_Generated_Image_iahpsziahpsziahp_covwyp.webp"
BOOK_IMG = "https://res.cloudinary.com/pwkdspnn/image/upload/f_auto,q_auto:good,w_1000/v1787777797/Screenshot_2026-08-26_at_21.55.18_kmdljy.png"
CONTACT_IMG = "https://res.cloudinary.com/pwkdspnn/image/upload/f_auto,q_auto:good,w_1000/q_auto:good/f_webp/788284500_18552596122075726_4025149319145713947_n_dgns44.jpg"
DOCS = "https://didiwalsonjack.com/wp-content/uploads/2026/10/"

PERSON = {
  "@type": "Person", "@id": SITE + "/#person",
  "name": NAME, "givenName": "Didi", "familyName": "Walson-Jack",
  "alternateName": ["Didi Walson-Jack", "Didi Esther Walson-Jack", "Diiarau Didi Esther Walson-Jack"],
  "honorificPrefix": "Mrs.", "honorificSuffix": "OON, mni",
  "gender": "Female", "nationality": {"@type": "Country", "name": "Nigeria"},
  "jobTitle": "Immediate Past Head of the Civil Service of the Federation of Nigeria (2024-2026)",
  "description": "Mrs. Didi Esther Walson-Jack, OON, mni, was the 20th Head of the Civil Service of the Federation of Nigeria (14 August 2024 to 27 August 2026). A lawyer and public administrator with 34 years of public service, she is now retired from active public service and works as an author, speaker and global advisor on institutions, leadership and reform.",
  "url": SITE + "/", "image": PHOTO, "email": "mailto:didi@didiwalsonjack.com", "telephone": "+2349095119999",
  "address": {"@type": "PostalAddress", "streetAddress": "Beautiful Gate Villa, Plot 812 Paul Unongo Crescent, Jabi", "addressLocality": "Abuja", "addressCountry": "NG"},
  "hasOccupation": [
    {"@type": "Occupation", "name": "Head of the Civil Service of the Federation of Nigeria", "occupationLocation": {"@type": "Country", "name": "Nigeria"}},
    {"@type": "Occupation", "name": "Federal Permanent Secretary, Federal Republic of Nigeria"},
    {"@type": "Occupation", "name": "Solicitor-General and Permanent Secretary, Ministry of Justice, Bayelsa State"}
  ],
  "alumniOf": [
    {"@type": "CollegeOrUniversity", "name": "Harvard Kennedy School"},
    {"@type": "CollegeOrUniversity", "name": "London School of Economics and Political Science"},
    {"@type": "CollegeOrUniversity", "name": "Harvard Business School"},
    {"@type": "CollegeOrUniversity", "name": "Mohammed Ibn Rashid School of Government"},
    {"@type": "CollegeOrUniversity", "name": "National Institute for Policy and Strategic Studies, Kuru"},
    {"@type": "CollegeOrUniversity", "name": "Nigerian Law School"},
    {"@type": "CollegeOrUniversity", "name": "University of Lagos"}
  ],
  "award": [
    "Award for Public Service at the 30th Anniversary of Bayelsa State (1 October 2026)",
    "2026 Global Service Award, Most Influential Persons of African Descent (23 September 2026)",
    "African Iconic Public Service Award and Hall of Fame Induction (23 May 2026)",
    "African Public Service Award, African Heritage Awards (11 April 2026)",
    "University of Lagos Distinguished Alumnus Award (17 October 2025)",
    "Officer of the Order of the Niger (OON), 2023"
  ],
  "memberOf": [{"@type": "Organization", "name": "Chartered Institute of Directors, Nigeria"}],
  "knowsAbout": ["Public sector transformation", "Federal civil service reform", "Public administration", "Legislative drafting",
                 "Institutional governance", "Digital government", "Performance management", "Leadership development", "Women in leadership"],
  "knowsLanguage": ["en"],
  "sameAs": ["https://x.com/Didi_WalsonJack", "https://www.linkedin.com/in/didi-esther-walson-jack-mcipm-oon-mni-7285a22a6/",
             "https://www.facebook.com/didi.walsonjack/", "https://www.instagram.com/didi.walsonjack/"],
  "mainEntityOfPage": SITE + "/about"
}
WEBSITE = {"@type": "WebSite", "@id": SITE + "/#website", "url": SITE + "/", "name": NAME + " | Official Website",
           "inLanguage": "en-NG", "publisher": {"@id": SITE + "/#person"}}
ORG_LOGO = {"@type": "ImageObject", "@id": SITE + "/#logo", "url": LOGO, "contentUrl": LOGO, "caption": NAME}

def faq(items):
    return {"@type": "FAQPage", "mainEntity": [{"@type": "Question", "name": q, "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in items]}

FAQ_MAIN = [
  ("Who is Mrs. Didi Esther Walson-Jack?", "Mrs. Didi Esther Walson-Jack, OON, mni, is a Nigerian lawyer and public administrator with 34 years of public service. She was the 20th Head of the Civil Service of the Federation of Nigeria, from 14 August 2024 to 27 August 2026, and is now retired from active public service."),
  ("When was Mrs. Walson-Jack Head of the Civil Service of the Federation?", "She was appointed by President Bola Ahmed Tinubu, GCFR (approved 17 July 2024), took office on 14 August 2024 and handed over on 27 August 2026."),
  ("What did she achieve as Head of the Civil Service?", "She led the final stretch of the Federal Civil Service Strategy and Implementation Plan 2021-2025 (FCSSIP25), set up seven reform War Rooms, expanded paperless work processes to 38 MDAs, introduced Service-Wise GPT, completed the Personnel Audit and Skills Gap Analysis, secured major staff welfare gains and began FCSSIP30 (2026-2030)."),
  ("What books has she written?", "Roses in the Thorns: An Autobiography (2017, Kraft Books, Ibadan), I Planted: A memoir of purposeful service (2026, Safari Books) and Beyond the Mandate: A Memoir on Leadership, Legacy & Labour of Public Service."),
  ("How can I contact her office or invite her to speak?", "Use the contact page at https://didiwalsonjack.com/contact, or write to didi@didiwalsonjack.com. The office is at Beautiful Gate Villa, Plot 812 Paul Unongo Crescent, Jabi, Abuja, Nigeria."),
]
FAQ_ABOUT = [
  ("Where did Mrs. Walson-Jack study?", "She holds an LL.B from the University of Lagos (1986) and a Bar Qualifying Certificate from the Nigerian Law School (1987). Her executive education includes the Executive Master of Public Administration at the London School of Economics (2025), Harvard Business School, the Harvard Kennedy School Certificate in Leadership for the 21st Century (September 2026) and the Nigeria Executive Leadership Programme at the Mohammed Ibn Rashid School of Government, Dubai (2025). She is a member of the National Institute (mni) from NIPSS, Kuru."),
  ("Where did she begin her public service career?", "She began as a legal draftswoman in Bayelsa State, rose to Solicitor-General and Permanent Secretary of the Ministry of Justice, joined the Federal Civil Service in 2009 and became a Federal Permanent Secretary in 2017."),
  ("What do OON and mni mean?", "OON means Officer of the Order of the Niger, a Nigerian national honour conferred in 2023. mni means member of the National Institute, awarded by the National Institute for Policy and Strategic Studies (NIPSS), Kuru."),
  ("Where can I download her CV and profile?", "Her curriculum vitae and introductory profile are available as PDF downloads on this page."),
]
FAQ_CAREER = [
  ("What positions has she held?", "Head of the Civil Service of the Federation (2024-2026); Federal Permanent Secretary in several ministries and offices from 2017, including Water Resources, Power, Niger Delta Affairs, Education and the Service Welfare Office; and Solicitor-General and Permanent Secretary, Ministry of Justice, Bayelsa State."),
  ("Where can I read the Head of Service legacy report and scorecard?", "Both are available as PDF downloads on the career page: the HCSF Legacy Report and the HCSF Scorecard."),
]
BOOKS = [
  {"@type": "Book", "name": "Roses in the Thorns: An Autobiography", "author": {"@id": SITE + "/#person"}, "datePublished": "2017", "publisher": {"@type": "Organization", "name": "Kraft Books Ltd, Ibadan"}, "inLanguage": "en", "url": "https://www.amazon.com/dp/B0DNY67X1C", "image": BOOK_IMG, "description": "A true-life story of resilience and the triumph of faith over adversity."},
  {"@type": "Book", "name": "I Planted: A Memoir of Purposeful Service", "author": {"@id": SITE + "/#person"}, "datePublished": "2026", "publisher": {"@type": "Organization", "name": "Safari Books"}, "inLanguage": "en", "description": "A memoir of a woman who chose to build when it would have been easier to wait."},
  {"@type": "Book", "name": "Beyond the Mandate: A Memoir on Leadership, Legacy & Labour of Public Service", "author": {"@id": SITE + "/#person"}, "inLanguage": "en", "description": "A reflection on leadership, service and reform in Nigeria's public service."},
]

PAGES = {
 "index": dict(path="/", type="WebPage", img=PHOTO,
   title="Mrs. Didi Esther Walson-Jack, OON, mni | Former Head of the Civil Service of Nigeria",
   desc="Official website of Mrs. Didi Esther Walson-Jack, OON, mni, 20th Head of the Civil Service of the Federation of Nigeria (2024-2026). 34 years of public service. Author, speaker and global advisor on institutions, leadership and reform.",
   kw="Didi Walson-Jack, Didi Esther Walson-Jack, Head of the Civil Service of the Federation, HCSF Nigeria, former Head of Service Nigeria, 20th HCSF, FCSSIP25, FCSSIP30, Federal Civil Service reform, Nigerian public service, keynote speaker, governance advisory, Roses in the Thorns, I Planted, Beyond the Mandate",
   ogt="Mrs. Didi Esther Walson-Jack, OON, mni | Former Head of the Civil Service of Nigeria", faq=FAQ_MAIN),
 "about": dict(path="/about", type="ProfilePage", img=PHOTO,
   title="About Mrs. Didi Esther Walson-Jack, OON, mni | Biography, Education & Profile",
   desc="Biography of Mrs. Didi Esther Walson-Jack, OON, mni: lawyer, Solicitor-General of Bayelsa State, Federal Permanent Secretary and 20th Head of the Civil Service of the Federation (2024-2026). Education, honours, CV and profile downloads.",
   kw="Didi Walson-Jack biography, Didi Walson-Jack CV, Didi Walson-Jack profile, Bayelsa Solicitor-General, Federal Permanent Secretary, 20th Head of Service Nigeria, NIPSS mni, University of Lagos law, LSE EMPA, Harvard Kennedy School, Harvard Business School, Mohammed Ibn Rashid School of Government",
   ogt="About Mrs. Didi Esther Walson-Jack, OON, mni | Biography & Profile", faq=FAQ_ABOUT),
 "career": dict(path="/career", type="CollectionPage", img=CAREER_IMG,
   title="Career & Achievements | Mrs. Didi Esther Walson-Jack, OON, mni",
   desc="Career record of Mrs. Didi Esther Walson-Jack, OON, mni: Head of the Civil Service of the Federation (14 August 2024 to 27 August 2026), FCSSIP25 reform, digital work processes, staff welfare, and earlier state and federal service. Legacy Report and Scorecard downloads.",
   kw="Didi Walson-Jack achievements, Head of Service Nigeria legacy report, HCSF scorecard, FCSSIP25, FCSSIP30, Service-Wise GPT, 1Gov paperless, seven War Rooms, civil service reform Nigeria, Permanent Secretary career, OON award",
   ogt="Career & Achievements | Mrs. Didi Esther Walson-Jack, OON, mni", faq=FAQ_CAREER),
 "books": dict(path="/books", type="CollectionPage", img=BOOK_IMG,
   title="Books & Publications | Mrs. Didi Esther Walson-Jack, OON, mni",
   desc="Books by Mrs. Didi Esther Walson-Jack, OON, mni: Roses in the Thorns (autobiography), I Planted (a memoir of purposeful service, 2026) and Beyond the Mandate (a memoir on leadership, legacy and labour of public service).",
   kw="Roses in the Thorns, I Planted memoir, Beyond the Mandate memoir, Didi Walson-Jack books, civil service memoir, Nigerian public service author, Safari Books",
   ogt="Books & Publications | Mrs. Didi Esther Walson-Jack, OON, mni", faq=None),
 "contact": dict(path="/contact", type="ContactPage", img=CONTACT_IMG,
   title="Contact & Advisory Enquiries | Mrs. Didi Esther Walson-Jack, OON, mni",
   desc="Contact the office of Mrs. Didi Esther Walson-Jack, OON, mni in Abuja for strategic advisory, leadership programmes, mentoring, board appointments and keynote speaking. didi@didiwalsonjack.com, +234 909 511 9999.",
   kw="Contact Didi Walson-Jack, public sector advisory Nigeria, keynote speaker Nigeria, leadership programme, institutional transformation advisory, Abuja",
   ogt="Contact & Advisory Enquiries | Mrs. Didi Esther Walson-Jack, OON, mni", faq=None),
}

def esc(t): return html.escape(t, quote=True)

def block(key, p):
    url = SITE + p["path"]
    graph = [WEBSITE, ORG_LOGO, PERSON]
    page = {"@type": p["type"], "@id": url + "#webpage", "url": url, "name": p["title"], "description": p["desc"],
            "inLanguage": "en-NG", "isPartOf": {"@id": SITE + "/#website"}, "about": {"@id": SITE + "/#person"},
            "primaryImageOfPage": {"@type": "ImageObject", "url": p["img"]}}
    if p["type"] == "ProfilePage": page["mainEntity"] = {"@id": SITE + "/#person"}
    if key == "about": page["hasPart"] = [{"@type": "DigitalDocument", "name": "Curriculum Vitae of Mrs. Didi Esther Walson-Jack", "encodingFormat": "application/pdf", "url": DOCS + "CV-OF-DIIARAU-DIDI-ESTHER-WALSON-JACK.pdf"},
                                          {"@type": "DigitalDocument", "name": "Profile of Mrs. Didi Walson-Jack", "encodingFormat": "application/pdf", "url": DOCS + "PROFILE-OF-MRS-DIDI-WALSON-JACK.pdf"}]
    if key == "career": page["hasPart"] = [{"@type": "DigitalDocument", "name": "HCSF Legacy Report, Mrs. Didi Walson-Jack", "encodingFormat": "application/pdf", "url": DOCS + "Mrs-DIdi-Walson-Jack-HCSF-Legacy-Report.pdf"},
                                          {"@type": "DigitalDocument", "name": "HCSF Scorecard, Mrs. Didi Walson-Jack", "encodingFormat": "application/pdf", "url": DOCS + "Mrs-DIdi-Walson-Jack-HCSF-ScoreCard.pdf"}]
    graph.append(page)
    crumbs = [("Home", SITE + "/")] + ([] if key == "index" else [(p["ogt"].split(" | ")[0], url)])
    graph.append({"@type": "BreadcrumbList", "itemListElement": [{"@type": "ListItem", "position": i + 1, "name": n, "item": u} for i, (n, u) in enumerate(crumbs)]})
    if p["faq"]: graph.append(faq(p["faq"]))
    if key == "books":
        PERSON_BOOKS = BOOKS
        graph.append({"@type": "ItemList", "name": "Books by " + NAME, "itemListElement": [{"@type": "ListItem", "position": i + 1, "item": b} for i, b in enumerate(PERSON_BOOKS)]})
    ld = json.dumps({"@context": "https://schema.org", "@graph": graph}, ensure_ascii=False, indent=1)
    return f'''<title>{esc(p["title"])}</title>
  <meta name="description" content="{esc(p["desc"])}" />
  <meta name="keywords" content="{esc(p["kw"])}" />
  <meta name="author" content="{esc(NAME)}" />
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />
  <meta name="theme-color" content="#3B1F4A" />
  <meta name="geo.region" content="NG-FC" />
  <meta name="geo.placename" content="Abuja, Nigeria" />
  <link rel="canonical" href="{url}" />
  <link rel="alternate" hreflang="en-NG" href="{url}" />
  <link rel="alternate" hreflang="x-default" href="{url}" />
  <link rel="alternate" type="text/plain" href="{SITE}/llms.txt" title="LLM-readable summary" />
  <meta property="og:site_name" content="{esc(NAME)} | Official Website" />
  <meta property="og:locale" content="en_NG" />
  <meta property="og:type" content="{'profile' if key == 'about' else 'website'}" />
  <meta property="og:title" content="{esc(p["ogt"])}" />
  <meta property="og:description" content="{esc(p["desc"])}" />
  <meta property="og:url" content="{url}" />
  <meta property="og:image" content="{p["img"]}" />
  <meta property="og:image:alt" content="{esc(NAME)}" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:site" content="@Didi_WalsonJack" />
  <meta name="twitter:creator" content="@Didi_WalsonJack" />
  <meta name="twitter:title" content="{esc(p["ogt"])}" />
  <meta name="twitter:description" content="{esc(p["desc"])}" />
  <meta name="twitter:image" content="{p["img"]}" />
  <script type="application/ld+json">
{ld}
  </script>'''

for key, p in PAGES.items():
    f = key + ".html"
    s = open(f, encoding="utf-8").read()
    hend = s.index("</head>")
    head, rest = s[:hend], s[hend:]
    head = re.sub(r'\s*<title>.*?</title>', '', head, flags=re.S)
    head = re.sub(r'\s*<meta\s+(?:name|property)="(?:description|keywords|author|robots|theme-color|geo\.[a-z]+|og:[a-z:_]+|twitter:[a-z]+)"\s+content="[^"]*"\s*/?>', '', head, flags=re.S)
    head = re.sub(r'\s*<link rel="(?:canonical|alternate)"[^>]*>', '', head)
    head = re.sub(r'\s*<script type="application/ld\+json">.*?</script>', '', head, flags=re.S)
    head = re.sub(r'\s*<!--\s*(?:SEO|OPEN GRAPH|SCHEMA)[^>]*-->', '', head, flags=re.I)
    anchor = re.search(r'<meta name="viewport"[^>]*>', head)
    ins = anchor.end()
    head = head[:ins] + "\n  " + "<!-- SEO / GEO / AEO (generated by tools/build_seo.py) -->\n  " + block(key, p) + head[ins:]
    open(f, "w", encoding="utf-8").write(head + rest)
    print("updated", f)
