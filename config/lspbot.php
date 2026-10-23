<?php

return [
    'threshold' => 0.55,
    'suggest_threshold' => 0.30,

    'fallback' => 'I don’t have enough verified information to answer that confidently. Please clarify your question or contact the appropriate administrator. I can guide you to De-Listment and R2-10, Inquiry, Form Generator, the BNH Checker, Tarheta and filing tools, and the Administrator Portal.',

    'greeting_words' => [
        'hi', 'hello', 'hey', 'hola', 'kumusta', 'kamusta', 'musta', 'good morning', 'good afternoon', 'good evening',
        'magandang umaga', 'magandang hapon', 'magandang gabi', 'magandang araw', 'lspbot', 'bot',
    ],

    'stopwords' => [
        'a', 'an', 'the', 'is', 'are', 'am', 'was', 'be', 'do', 'does', 'did', 'i', 'me', 'my', 'we', 'you', 'your', 'to', 'of',
        'in', 'on', 'for', 'at', 'by', 'from', 'as', 'with', 'and', 'or', 'it', 'its', 'this', 'that', 'there', 'about', 'can',
        'could', 'would', 'should', 'will', 'please', 'pls', 'plz', 'how', 'what', 'where', 'when', 'which', 'who', 'why', 'want',
        'need', 'like', 'see', 'show', 'tell', 'give', 'get', 'know', 'some', 'any', 'so', 'if', 'then', 'just', 'ok', 'okay',
        'ang', 'ng', 'sa', 'mga', 'ako', 'ko', 'mo', 'ka', 'ba', 'po', 'opo', 'naman', 'paano', 'pano', 'saan', 'san', 'ano',
        'anong', 'kailan', 'kelan', 'pwede', 'puwede', 'maaari', 'na', 'ay', 'si', 'ni', 'kay', 'yung', 'yong', 'nito', 'niyo',
        'ninyo', 'namin', 'natin', 'gusto', 'nais', 'mag', 'nag', 'pag', 'ma', 'ito', 'iyan', 'iyon', 'din', 'rin',
        'lang', 'lamang', 'kasi', 'kung', 'para', 'dito', 'doon', 'diyan', 'o', 'ipakita', 'makikita', 'mahanap',
        'daw', 'may', 'ulit', 'bakit',
    ],

    'synonyms' => [
        'edit' => ['change', 'modify', 'update', 'correct', 'fix', 'baguhin', 'palitan', 'ayusin', 'itama', 'mababago', 'mabago', 'binago', 'mag-edit', 'edit'],
        'delist' => ['delisting', 'delisted', 'delistment', 'tanggal', 'tanggalin', 'alisin'],
        'print' => ['imprenta', 'ilimbag', 'limbagin', 'printout', 'printer'],
        'find' => ['search', 'locate', 'lookup', 'look', 'hanap', 'hanapin', 'maghanap'],
        'add' => ['create', 'new', 'insert', 'dagdag', 'magdagdag', 'idagdag'],
        'member' => ['members', 'record', 'records', 'miyembro', 'kapatid'],
        'access' => ['permission', 'permissions', 'allowed', 'allow', 'pahintulot', 'payagan'],
        'file' => ['filing', 'filed', 'files'],
        'form' => ['forms', 'porma'],
        'thank' => ['thanks', 'thankyou', 'salamat', 'thx', 'ty'],
        'help' => ['tulong', 'tulungan', 'assist', 'assistance', 'patulong', 'assistant'],
        'secretary' => ['secretaries', 'kalihim'],
        'monthly' => ['buwanan'],
        'weekly' => ['lingguhan'],
        'password' => ['pasword', 'passcode'],
        'slow' => ['mabagal', 'lag', 'laggy', 'bagal'],
        'duplicate' => ['duplicates', 'double', 'doble', 'magkapareho', 'pareho'],
        'error' => ['errors', 'mali'],
    ],

    'phrases' => [
        'r2 10' => 'r210',
        'de listment' => 'delistment',
        'de list' => 'delist',
        'thank you' => 'thank',
        'multi auth' => 'multiauth',
        'smart helper' => 'smarthelper',
        'god s words' => 'godswords',
        'gods words' => 'godswords',
    ],

    'entries' => [

        // ===== Part 1: Everyday conversation =====================================================
        'greeting' => [
            'suggest' => false,
            'questions' => ['hello', 'hi there', 'kumusta', 'good morning', 'kumusta po'],
            'keywords' => [],
            'answer' => 'Hello! Welcome to LSPBot. How can I help you today?',
        ],
        'goodbye' => [
            'suggest' => false,
            'questions' => ['bye', 'goodbye', 'thats all', 'hanggang sa muli', 'paalam', 'see you'],
            'keywords' => ['bye', 'goodbye', 'paalam'],
            'answer' => 'Goodbye! Have a great day. Feel free to come back if you need help.',
        ],
        'thanks' => [
            'suggest' => false,
            'questions' => ['thank you', 'thanks', 'thank you po', 'salamat', 'salamat po', 'salamat po sa tulong', 'thanks a lot', 'thank you so much'],
            'keywords' => ['thank'],
            'answer' => 'You’re welcome! I’m happy to help.',
            'answer_tl' => 'Walang anuman! Nandito lang ang LSPBot kung kailangan mo ulit ng tulong.',
        ],
        'identity' => [
            'suggest' => false,
            'questions' => ['who are you', 'are you a real person', 'are you a bot', 'what is your name', 'sino ka', 'ano ang pangalan mo'],
            'keywords' => [],
            'answer' => 'I’m LSPBot, your virtual assistant. I can help answer questions about LSPDMS and guide you through common tasks.',
        ],
        'capabilities' => [
            'suggest' => false,
            'questions' => ['what can you do', 'what can i ask you', 'how can you help me', 'help', 'ano ang kaya mo', 'ano ang magagawa mo'],
            'keywords' => ['what can you do', 'what can i ask'],
            'answer' => 'I can explain system features, help you find pages, answer common questions, and guide you through everyday tasks. If your account has lookup access, I can also answer brief record questions and counts, like “How many MS are there?” or “Find Juan Dela Cruz”. I only guide and locate features—I cannot edit or change records.',
        ],
        'request_help' => [
            'suggest' => false,
            'questions' => ['help me', 'i need assistance', 'patulong naman', 'patulong po', 'i need help'],
            'keywords' => ['patulong'],
            'answer' => 'Of course! What are you trying to do? I’ll guide you through it.',
        ],
        'unclear_question' => [
            'suggest' => false,
            'questions' => ['how does this work', 'paano ito', 'how does it work'],
            'keywords' => [],
            'answer' => 'I can explain it. Which feature or process are you referring to?',
        ],
        'repeat_explanation' => [
            'suggest' => false,
            'questions' => ['i dont understand', 'explain it simply', 'di ko gets', 'hindi ko maintindihan', 'explain again', 'ulitin mo'],
            'keywords' => ['dont understand', 'di ko gets', 'hindi ko maintindihan', 'explain simply'],
            'answer' => 'No problem! I’ll explain it in simpler terms. Which part would you like me to clarify?',
        ],
        'frustration' => [
            'suggest' => false,
            'questions' => ['this is annoying', 'nakakainis naman', 'nothing works', 'nakakainis', 'ang hirap naman'],
            'keywords' => ['annoying', 'nakakainis', 'nothing works'],
            'answer' => 'I understand that this can be frustrating. Tell me what happened, and I’ll help you find the next step.',
        ],
        'misunderstanding' => [
            'suggest' => false,
            'questions' => ['thats not what i asked', 'you misunderstood me', 'mali ang sagot mo', 'hindi yan ang tinanong ko'],
            'keywords' => ['not what i asked', 'misunderstood', 'hindi yan ang tinanong'],
            'answer' => 'Sorry about that. Could you clarify what you wanted to know? I’ll try again.',
        ],
        'ask_for_directions' => [
            'suggest' => false,
            'questions' => ['where do i start', 'what should i do first', 'i dont know where to start', 'saan ako magsisimula', 'ano ang uunahin ko'],
            'keywords' => ['where to start', 'where do i start', 'do first', 'magsisimula'],
            'answer' => 'No problem! Are you trying to find a record, update information, generate a form, or learn about another system feature? Tell me what you’re trying to accomplish and I’ll guide you.',
        ],
        'off_topic' => [
            'suggest' => false,
            'questions' => ['tell me a joke', 'whats a fun fact', 'joke', 'magkwento ka', 'magjoke ka'],
            'keywords' => ['joke', 'fun fact'],
            'answer' => 'Sure! Why did the computer go to the doctor? It had a virus! I can also help with LSPDMS questions whenever you need.',
        ],

        // ===== Part 2: Knowledge and navigation ==================================================
        'learn_more' => [
            'title' => 'Home and God’s Words',
            'questions' => [
                'Where can I learn more?', 'Where can I read God’s Words?', 'Saan ako makakabasa ng God’s Words?',
                'Where are the news and magazines?', 'Where can I see activities?',
            ],
            'keywords' => ['godswords', 'magazines', 'news', 'activities', 'learn more', 'true church'],
            'answer' => 'Visit Home to learn more about the True Church through Activities, News, Magazines, God’s Words, and many more.',
            'answer_tl' => 'Pumunta sa Home page. Makikita roon ang Activities, News, Magazines, God’s Words, at marami pang iba.',
        ],
        'dashboard' => [
            'title' => 'Dashboard',
            'feature' => 'dashboard_allow',
            'route' => 'dashboard',
            'questions' => [
                'Where can I see my records easily?', 'Where do I monitor records?', 'Saan ko makikita lahat ng records?',
                'Where can I see the statistics?', 'Show me the charts', 'Where is the dashboard?',
                'Saan makikita ang dashboard?', 'Saan makikita ang records by purok?',
            ],
            'keywords' => ['dashboard', 'charts', 'statistics', 'records by purok', 'monitor records'],
            'answer' => 'Go to Dashboard to view and monitor records and the information available to your account. It shows record totals, Purok breakdowns, CFO groups, and updating progress.',
            'answer_tl' => 'Pumunta sa Dashboard para madaling makita at ma-monitor ang available records.',
        ],
        'inquiry' => [
            'title' => 'Inquiry / Modify',
            'feature' => 'Inquire_Allow',
            'route' => 'inquire-modify',
            'questions' => [
                'Where can I search and edit data?', 'Saan ako mag-e-edit ng records?', 'Paano mag-edit ng data?',
                'Where can I edit data?', 'How do I edit member data?', 'How do I update a record?',
                'Where can I change a member’s birthday?', 'Paano baguhin ang registry number?',
                'Saan ko mababago ang kapisanan ng member?', 'Paano mag-edit ng data ng kapatid?',
            ],
            'keywords' => ['edit data', 'edit member data', 'edit record', 'edit member', 'inquiry', 'inquire', 'registry number', 'birthday', 'kapisanan'],
            'not' => ['profile', 'password', 'signatory', 'secretary', 'pastor', 'missing'],
            'answer' => 'Go to Inquiry to search for records and edit data, subject to your permissions. I can only direct you there; I cannot edit or change records for you.',
            'answer_tl' => 'Pumunta sa Inquiry para mag-search at mag-edit ng records, depende sa permissions mo.',
        ],
        'missing_data' => [
            'title' => 'Updating missing records',
            'feature' => 'Inquire_Allow',
            'route' => 'inquire-modify',
            'questions' => [
                'Some records are missing', 'How do I update missing records?', 'May kulang na data, paano ko i-update?',
                'What if it’s missing?', 'What if its missing', 'How do I fill in missing data?', 'May kulang na data',
            ],
            'keywords' => ['missing data', 'missing record', 'kulang na data', 'registry updating graph', 'incomplete'],
            'not' => ['report', 'older'],
            'boost' => 0.05,
            'answer' => 'You can update records through Inquiry. Alternatively, open Dashboard, click the Registry Updating Graph, and review the missing data so you can edit the relevant records there.',
            'answer_tl' => 'Puwede kang mag-update sa Inquiry. Puwede ring pumunta sa Dashboard, i-click ang Registry Updating Graph, at tingnan kung aling data ang kailangang i-update.',
        ],
        'form_generator' => [
            'title' => 'Form Generator',
            'feature' => 'form_generator_allow',
            'route' => 'form-generator',
            'questions' => [
                'Where can I automate forms?', 'Can I generate forms automatically?', 'How do I print forms?',
                'Where do I generate R4-01?', 'Where can I print the weekly form?', 'Paano mag print ng form?',
                'Saan makikita ang Resumen?', 'Paano mag-generate ng R4-02?',
            ],
            'keywords' => ['print', 'generate form', 'form generator', 'r4-01', 'r4-02', 'r4-03', 'resumen', 'weekly form', 'monthly form'],
            'not' => ['monthly session'],
            'answer' => 'Go to Form Generator to automatically generate Weekly Forms and Monthly Forms. Choose the Monthly or Weekly section, select the form or Resumen generator you need, then use Generate / Print and your browser’s print dialog.',
        ],
        'tarheta_checker' => [
            'title' => 'Tarheta and Status Checker',
            'feature' => 'tarheta_status_allow',
            'route' => 'tarheta-status',
            'questions' => [
                'Where can I monitor Tarheta and filing?', 'Where do I update the filing number?',
                'How do I check the Tarheta status?', 'Paano i-check ang tarheta status?', 'Saan ko ia-update ang filing number?',
            ],
            'keywords' => ['tarheta', 'filing status', 'filing number', 'status checker'],
            'answer' => 'Go to Tarheta and Status Checker to monitor the available Tarheta and filing statuses. You can also update Tarheta and filing details there.',
        ],
        'profile' => [
            'title' => 'Profile',
            'questions' => [
                'Where do I edit my profile?', 'Where can I change my profile picture or password?', 'Where is my profile?',
                'Saan ko babaguhin ang profile ko?',
            ],
            'keywords' => ['profile', 'profile picture'],
            'boost' => 0.05,
            'answer' => 'Go to Profile to access your account information, profile picture options, and password settings, where available.',
        ],
        'change_password' => [
            'title' => 'Changing your password',
            'questions' => ['Paano magpalit ng password?', 'How do I change my password?', 'Where can I change my password?', 'Paano baguhin ang password?'],
            'keywords' => ['password'],
            'not' => ['forgot', 'multiauth', 'enable'],
            'answer' => 'Go to Profile and look for the password change option.',
            'answer_tl' => 'Pumunta sa Profile at hanapin ang password change option.',
        ],

        // ===== Part 3: Permissions and confidentiality ===========================================
        'error_403' => [
            'title' => '403 Unauthorized error',
            'questions' => [
                'My screen says Unauthorized 403', 'Why can’t I open this page?', 'Bakit ayaw mag-open? 403 daw.',
                'I got a 403 forbidden error', 'Bakit hindi ko mabuksan ang page?',
            ],
            'keywords' => ['403', 'unauthorized', 'forbidden', 'cant open', 'cannot open', 'ayaw mag open'],
            'boost' => 0.05,
            'answer' => 'A 403 Forbidden error means your account does not have permission to access that section. The Head Secretary may need to grant the appropriate access. Please go back and contact them if you believe you should have access.',
            'answer_tl' => 'Ibig sabihin, wala kang permission para ma-access ang section na iyon. Bumalik muna at makipag-ugnayan sa Head Secretary kung kailangan mo ng access.',
        ],
        'mk_r210_confidentiality' => [
            'title' => 'MK and R2-10 confidentiality',
            'questions' => [
                'Why can’t I see MK details?', 'Why is R2-10 Deliberation hidden?', 'Why is MK hidden?',
                'Bakit hindi ko makita ang MK?', 'Bakit nakatago ang R2-10 deliberation?',
            ],
            'keywords' => ['mk details', 'deliberation', 'confidential', 'hidden', 'nakatago'],
            'not' => ['add', 'delist', 'endorsement'],
            'answer' => 'MK and R2-10 Deliberation details are sensitive and must remain confidential. Your account may not have the required permission. Contact the Head Secretary if you need authorized access.',
        ],
        'manage_secretary_permissions' => [
            'title' => 'Controlling secretary access',
            'questions' => [
                'How do I control what secretaries can access?', 'How do I give a secretary access?',
                'Paano ko makokontrol ang access ng mga kalihim?',
            ],
            'keywords' => ['control secretary', 'secretary permission', 'secretary access', 'give secretary'],
            'answer' => 'Go to Administrator Portal → Secretaries Management, if you have the required administrative permissions.',
        ],
        'permission_missing' => [
            'title' => 'Missing features or access',
            'questions' => [
                'Why can’t I see this feature?', 'Bakit wala sa account ko?', 'How do I get access?',
                'Who can give me permission?', 'Paano ako magkakaroon ng access?', 'Why is a menu missing?',
            ],
            'keywords' => ['get access', 'feature missing', 'menu missing', 'wala sa account', 'head secretary'],
            'answer' => 'The feature may be restricted by your assigned permissions. Contact the Head Secretary or appropriate administrator if you believe access should be granted.',
        ],

        // ===== Part 4: Monthly Session ===========================================================
        'monthly_session_schedule' => [
            'title' => 'Monthly Session schedule',
            'questions' => [
                'When can I do my November Monthly Session?', 'Kailan ang monthly para sa November?',
                'Kailan gagawin ang monthly para sa November?', 'When do I prepare the monthly session?',
                'Why can’t I make my November monthly?', 'When do I make the December monthly?',
            ],
            'keywords' => ['monthly session', 'monthly', 'november monthly'],
            'not' => ['twice', 'another', 'already submitted', 'nakapag monthly'],
            'answer' => 'The November Monthly Session is prepared during the last two weeks of October, the preceding month. Monthly Sessions follow this schedule so they can be prepared before their applicable month. Only one submission is allowed per month.',
            'answer_tl' => 'Ginagawa ang November Monthly Session sa huling dalawang linggo ng October, o ng buwan bago ang reporting month.',
        ],
        'monthly_session_unavailable' => [
            'title' => 'Monthly Session is disabled',
            'questions' => [
                'Why won’t the system let me start my monthly?', 'Bakit disabled pa ang Monthly Session?',
                'Why is the monthly session disabled?', 'Monthly session button is disabled', 'Bakit ayaw mag-start ng monthly?',
            ],
            'keywords' => ['monthly disabled', 'monthly session disabled', 'disabled monthly', 'cant start monthly', 'ayaw monthly'],
            'boost' => 0.05,
            'answer' => 'Monthly Session is available during the last two weeks of the preceding month, not during the first half of that month. Please check whether the allowed period has opened.',
        ],
        'monthly_session_once_per_month' => [
            'title' => 'One Monthly Session per month',
            'questions' => [
                'Can I submit my monthly twice?', 'Why can’t I submit another monthly?', 'Nakapag-monthly na ako, pwede ulit?',
                'Can I submit the monthly again?', 'Pwede bang mag-submit ng monthly ulit?',
            ],
            'keywords' => ['monthly twice', 'another monthly', 'monthly again', 'nakapag monthly', 'submit monthly twice'],
            'boost' => 0.05,
            'answer' => 'Only one Monthly Session submission is allowed per month. If you’ve already submitted the applicable month’s session, you must wait until the next month’s permitted submission period.',
            'answer_tl' => 'Isang Monthly Session submission lang ang pinapayagan kada buwan. Kailangan mong hintayin ang susunod na permitted submission period.',
        ],
        'monthly_session_next_month' => [
            'title' => 'Next Monthly Session after submitting',
            'questions' => [
                'I already submitted this month. When can I submit again?', 'When can I submit the next monthly?',
                'Kailan ako puwedeng mag-monthly ulit?',
            ],
            'keywords' => ['submit again', 'next monthly', 'already submitted'],
            'boost' => 0.05,
            'answer' => 'You must wait until the last two weeks of the preceding month for the next reporting month. For example, the December Monthly Session is prepared during the last two weeks of November.',
        ],
        'monthly_session_example' => [
            'title' => 'Which month to prepare',
            'questions' => [
                'Do I do November’s monthly in November?', 'Do I make the monthly in the same month?', 'Is the monthly done in the same month?',
            ],
            'keywords' => ['same month', 'in november', 'in december'],
            'answer' => 'No. You prepare the November Monthly Session during the last two weeks of October. In general, the session for a particular month is prepared during the last two weeks of the month before it.',
        ],

        // ===== Part 5: Administrator Portal ======================================================
        'administrator_portal' => [
            'title' => 'Administrator Portal',
            'questions' => ['Where can I configure system settings?', 'Where is the Administrator Portal?', 'Saan ang admin portal?'],
            'keywords' => ['administrator portal', 'admin portal', 'system settings'],
            'answer' => 'Go to Administrator Portal to access the administrative functions available to your account.',
        ],
        'signatory_management' => [
            'title' => 'Signatory management',
            'questions' => ['Where can I manage signatories?', 'Where do I send signatories?', 'Saan ko ia-update ang mga signatory?'],
            'keywords' => ['signatory', 'signatories'],
            'not' => ['pastor', 'lcode'],
            'answer' => 'Go to Administrator Portal and locate the appropriate signatory management function.',
        ],
        'r210_settings' => [
            'title' => 'R2-10 eligibility settings',
            'questions' => ['Where do I configure R2-10 eligibility?', 'Saan kino-configure ang R2-10?', 'Where are the R2-10 requirements?'],
            'keywords' => ['configure r210', 'r210 eligibility', 'r210 settings', 'r210 requirements', 'kino configure'],
            'boost' => 0.05,
            'answer' => 'Go to Administrator Portal to access the R2-10 Eligibility settings, if authorized.',
            'answer_tl' => 'Pumunta sa Administrator Portal at hanapin ang R2-10 Eligibility settings kung may access ka.',
        ],
        'authentication_settings' => [
            'title' => 'Multi Auth and Forgot Password settings',
            'questions' => ['How do I enable Multi Auth?', 'Where do I enable Forgot Password?', 'Where is two factor authentication?'],
            'keywords' => ['multiauth', 'forgot password', 'authentication'],
            'answer' => 'Go to Administrator Portal to access the Multi Auth and Forgot Password settings. Only authorized administrators should change them.',
        ],
        'database_import' => [
            'title' => 'Database import',
            'questions' => [
                'I have a MySQL file. How do I migrate my locale’s data?', 'How do I import a sql file?',
                'Where do I import the database?', 'Paano mag-import ng database?',
            ],
            'keywords' => ['mysql', 'sql file', 'migrate', 'database import', 'import database'],
            'not' => ['excel'],
            'answer' => 'Go to Administrator Portal and use the appropriate database import function. The importer accepts only LSPDMS-formatted .sql files. Verify compatibility and ensure a suitable backup exists before importing.',
        ],
        'document_settings' => [
            'title' => 'Document Settings',
            'questions' => [
                'Where can I change the pastor’s name, PD, local secretaries, district name, LCode, DCode, or S number?',
                'Where do I change the pastor name?', 'Where can I change the district name?', 'Saan babaguhin ang LCode?',
            ],
            'keywords' => ['document settings', 'pastor', 'lcode', 'dcode', 'district name', 's number', 'local secretaries'],
            'answer' => 'Go to Administrator Portal → Document Settings to manage the available document and signatory settings.',
        ],
        'secretary_activity' => [
            'title' => 'Secretary Activity',
            'questions' => [
                'What activities have my secretaries performed?', 'How do I monitor their actions?',
                'Saan makikita ang mga ginawa ng mga kalihim?', 'Where can I see the activity log?',
            ],
            'keywords' => ['secretary activity', 'activity log', 'secretary performed', 'monitor actions', 'ginawa ng kalihim'],
            'answer' => 'Go to Administrator Portal → Secretary Activity to review the activities recorded by the system, subject to your permissions.',
            'answer_tl' => 'Pumunta sa Administrator Portal → Secretary Activity para makita ang mga activity na na-record ng system.',
        ],
        'secretaries_management' => [
            'title' => 'Secretaries Management',
            'questions' => ['Where do I manage secretary access?', 'Where is Secretaries Management?', 'Saan ko pamamahalaan ang access ng kalihim?'],
            'keywords' => ['secretaries management', 'manage secretary'],
            'answer' => 'Go to Administrator Portal → Secretaries Management to configure the functions secretaries can access and operate.',
        ],
        'revoke_access' => [
            'title' => 'Revoking access',
            'questions' => ['How do I revoke someone’s access?', 'How do I remove a secretary’s access?', 'Paano tanggalin ang access ng isang tao?'],
            'keywords' => ['revoke', 'remove access', 'tanggalin ang access'],
            'boost' => 0.05,
            'answer' => 'Use the appropriate administrative access-management function and follow your organization’s authorization procedures. Document Settings is for document and signatory settings; access revocation may be managed separately.',
        ],

        // ===== Part 6: De-Listment, R2-10, BNH ===================================================
        'delistment_portal' => [
            'title' => 'De-Listment Portal',
            'feature' => 'Delistment_allow',
            'route' => 'de-listment',
            'questions' => [
                'Where can I make an endorsement?', 'Where can I manage De-Listment?', 'How do I delist a member?',
                'Where can I endorse R2-10?', 'Where do I remove an inactive member?', 'Paano mag delist ng member?',
                'Saan ang R2-10 endorsement?', 'Paano i-endorse ang R2-10?',
            ],
            'keywords' => ['delist', 'delistment', 'endorsement', 'endorse', 'inactive member', 'r210'],
            'not' => ['configure', 'settings', 'requirements', 'confidential', 'hidden', 'deliberation'],
            'answer' => 'Go to De-Listment Portal to access the available endorsement and De-Listment functions. The page shows R2-10 eligibility and endorsement status; endorse eligible records there, then use the Delist action when the workflow permits it.',
        ],
        'mk_endorsement' => [
            'title' => 'Adding MK to an endorsement',
            'feature' => 'Delistment_allow',
            'route' => 'de-listment',
            'questions' => ['How do I add MK to an endorsement?', 'Paano magdagdag ng MK sa endorsement?', 'Can I endorse an MK?'],
            'keywords' => ['add mk', 'mk endorsement', 'mk to endorsement'],
            'boost' => 0.1,
            'answer' => 'Use the applicable endorsement function in De-Listment Portal, subject to your permissions and the system’s business rules.',
        ],
        'view_delisted' => [
            'title' => 'Viewing delisted records',
            'feature' => 'Delistment_allow',
            'route' => 'de-listment',
            'questions' => ['Where can I see delisted records?', 'Saan makikita ang mga na-delist?', 'Show delisted members'],
            'keywords' => ['see delisted', 'view delisted', 'delisted records', 'na delist'],
            'boost' => 0.05,
            'answer' => 'Go to De-Listment Portal to view the available delisted records.',
        ],
        'delist_mk' => [
            'title' => 'De-Listing an MK',
            'feature' => 'Delistment_allow',
            'route' => 'de-listment',
            'questions' => ['Where do I De-List MK?', 'Paano mag-delist ng MK?', 'How do I delist an MK?'],
            'keywords' => ['delist mk', 'mk delist'],
            'boost' => 0.1,
            'answer' => 'The relevant De-Listment functions are in De-Listment Portal. Whether a specific change is allowed depends on the applicable business rules and your permissions.',
        ],
        'bnh_checker' => [
            'title' => 'BNH na Hapunan Checker',
            'feature' => 'filter_bnh_helper_allow',
            'route' => 'filter-bnh-helper',
            'questions' => [
                'What is BNH na Hapunan Checker?', 'Para saan ang BNH Checker?', 'What is the BNH helper?',
                'Where is the BNH resumen?', 'How do I see the minus count?', 'Saan ang BNH helper?',
            ],
            'keywords' => ['bnh', 'hapunan', 'holy supper', 'minus count', 'minuses', 'qualified'],
            'answer' => 'The BNH na Hapunan Checker is an automated form for LSP that helps determine the minuses, identify who is eligible for Holy Supper (BNH), and calculate how much should be deducted for each Purok Grupo.',
        ],

        // ===== Part 7: Data integrity ============================================================
        'duplicate_prevention' => [
            'title' => 'Duplicate prevention',
            'questions' => ['How do you prevent double entries?', 'Paano ninyo sinisigurado na walang duplicate?', 'How does the system avoid duplicates?'],
            'keywords' => ['prevent duplicate', 'avoid duplicate', 'double entries', 'walang duplicate'],
            'answer' => 'LSPDMS validates records during data entry and checks whether the record or Registry Number already exists. This helps prevent duplicate entries.',
        ],
        'registry_validation' => [
            'title' => 'Registry Number errors',
            'questions' => ['Why can’t I add this Registry Number?', 'Registry number already exists', 'Bakit hindi tanggap ang registry number?'],
            'keywords' => ['add registry number', 'registry number error', 'registry number exists'],
            'answer' => 'The system checks the Registry Number against its validation rules and existing records. Review the error message and verify the existing record before proceeding.',
        ],
        'smart_helper' => [
            'title' => 'Smart Helper',
            'questions' => [
                'What is Smart Helper?', 'Paano malalaman kung duplicate ang record?', 'How do I know if a record is a duplicate?',
                'How do I check for duplicates?',
            ],
            'keywords' => ['smarthelper', 'check duplicate', 'know duplicate', 'malalaman duplicate'],
            'answer' => 'The Smart Helper Tool in Inquiry / Modify identifies names that may be closely related or similar, helping secretaries review possible duplicate records.',
            'answer_tl' => 'Gamitin ang Smart Helper Tool sa Inquiry / Modify. Tinutulungan ka nitong makita ang mga pangalang posibleng magkapareho, pero kailangan pa ring i-verify kung duplicate talaga.',
        ],
        'similar_names' => [
            'title' => 'Similar names',
            'questions' => ['Two people have similar names. Are they duplicates?', 'Are similar names duplicates?', 'Magkapareho ang pangalan, duplicate ba?'],
            'keywords' => ['similar names', 'similar name', 'same name'],
            'answer' => 'Not necessarily. Similar names can belong to different people. Review the suggested matches and compare relevant details before deciding.',
        ],
        'duplicate_found' => [
            'title' => 'A possible duplicate was found',
            'questions' => ['Smart Helper found a possible duplicate. What should I do?', 'What do I do with a possible duplicate?', 'May nakitang duplicate, ano ang gagawin ko?'],
            'keywords' => ['possible duplicate', 'found duplicate', 'duplicate found'],
            'boost' => 0.05,
            'answer' => 'Review the suggested records and verify their details. If you’re unsure, ask an authorized person to help determine whether they represent the same person.',
        ],
        'record_save_error' => [
            'title' => 'Record will not save',
            'questions' => ['I can’t save my record.', 'Cannot save record', 'Hindi ma-save ang record', 'Save is not working'],
            'keywords' => ['cant save', 'cannot save', 'save error', 'hindi ma save', 'ayaw mag save'],
            'answer' => 'Check for missing required fields or validation messages. If the issue continues, note the error and contact support rather than repeatedly submitting the same information.',
        ],

        // ===== Part 8: Troubleshooting ===========================================================
        'page_blank' => [
            'title' => 'Blank page',
            'questions' => ['The page is blank.', 'Nothing is showing.', 'Blangko ang page', 'Walang lumalabas'],
            'keywords' => ['blank', 'blangko', 'nothing showing', 'walang lumalabas'],
            'answer' => 'Try refreshing the page once. If it remains blank, tell me which page you opened and whether an error appeared.',
        ],
        'slow_system' => [
            'title' => 'Slow system',
            'questions' => ['The system is so slow.', 'Ang bagal ng system', 'System is lagging'],
            'keywords' => ['slow', 'lagging'],
            'answer' => 'I’m sorry you’re experiencing that. Does the slowdown happen on one particular page or throughout the system?',
        ],
        'button_not_working' => [
            'title' => 'Button does not respond',
            'questions' => ['Nothing happens when I click.', 'The button is not working', 'Walang nangyayari kapag nag-click ako'],
            'keywords' => ['nothing happens', 'button not working', 'cant click', 'walang nangyayari'],
            'answer' => 'Check whether the button is disabled or whether an error message appears. Which button are you clicking?',
        ],
        'import_failed' => [
            'title' => 'Failed Excel import',
            'questions' => ['My Excel import failed.', 'Excel import error', 'Bigo ang import ko'],
            'keywords' => ['import failed', 'excel import', 'import error', 'bigo ang import'],
            'answer' => 'Check the import review screen and any row-level validation messages. If you share the error text, I can help explain it.',
        ],
        'import_partial' => [
            'title' => 'Rejected import rows',
            'questions' => ['Some rows were rejected.', 'Why were some rows rejected?', 'May mga row na na-reject'],
            'keywords' => ['rows rejected', 'rejected rows', 'row rejected', 'na reject'],
            'answer' => 'Some rows may contain missing fields, invalid values, or duplicate information. Review the errors for each rejected row before correcting the source data.',
        ],
        'import_retry' => [
            'title' => 'Retrying an import',
            'questions' => ['Should I upload the file again?', 'Can I re-import the file?', 'Ia-upload ko ba ulit ang file?'],
            'keywords' => ['upload again', 'reimport', 'import again', 'upload file again'],
            'answer' => 'First, check whether the previous import saved any records. Retrying without checking may create duplicates or repeat changes.',
        ],
        'missing_report' => [
            'title' => 'Report missing older data',
            'questions' => ['Why is my report missing older data?', 'Report is missing old records', 'Bakit kulang ang report ko?'],
            'keywords' => ['report missing', 'missing report', 'older data', 'kulang ang report'],
            'boost' => 0.05,
            'answer' => 'Check the reporting period, filters, and available historical records. Missing data should be investigated rather than assumed to be zero.',
        ],
        'lost_work' => [
            'title' => 'Lost information',
            'questions' => ['I lost the information I entered.', 'My data disappeared', 'Nawala ang inilagay kong data'],
            'keywords' => ['lost information', 'lost data', 'data disappeared', 'nawala', 'lost work'],
            'answer' => 'I’m sorry that happened. Check whether the system saved a draft or whether the information is still available. Avoid creating duplicate entries while checking.',
        ],
        'report_bug' => [
            'title' => 'Reporting a bug',
            'questions' => ['I want to report a bug.', 'Report a bug', 'May bug sa system'],
            'keywords' => ['bug', 'report bug'],
            'answer' => 'Please describe what you were doing, what you expected to happen, what actually happened, and any error message you saw.',
        ],
        'unresolved_issue' => [
            'title' => 'Issue still not solved',
            'suggest' => false,
            'questions' => ['I already tried that. It still doesn’t work.', 'It still doesnt work', 'Hindi pa rin gumagana'],
            'keywords' => ['still doesnt work', 'already tried', 'hindi pa rin gumagana'],
            'answer' => 'Sorry that didn’t help. Let’s try a different approach. What happened after you followed those steps?',
        ],

        // ---- TEMPLATE ------------------------------------------------------------------------
        // 'unique_key' => [
        //     'title' => 'Short name', 'feature' => null, 'route' => null,
        //     'questions' => ['How do I …?'], 'keywords' => ['phrase'],
        //     'not' => ['word that must NOT appear'], 'boost' => 0.05,
        //     'answer' => 'English reply.', 'answer_tl' => 'Optional Tagalog reply.',
        // ],
    ],
];