<?php

namespace App\Services;

/**
 * Extra things LSPBot knows, merged on top of config/lspbot.php by LspBotMatcher.
 * Same entry format as the config (questions, keywords, not, boost, feature, route,
 * answer / answer_tl, suggest). 'extend' adds questions and keywords to entries that
 * already exist in the config.
 */
final class LspBotKnowledge
{
    /** @return array{greeting_words: list<string>, synonyms: array<string, list<string>>, phrases: array<string, string>, extend: array<string, array>, entries: array<string, array>} */
    public static function all(): array
    {
        return [
            'greeting_words' => ['hello po', 'hi po', 'helo', 'hellow', 'hai', 'uy', 'yo', 'good am', 'good pm', 'gud morning', 'gud am', 'gud pm', 'gandang umaga', 'gandang gabi', 'gandang hapon'],

            'synonyms' => [
                'task' => ['tasks', 'gawain', 'assignment', 'assignments'],
                'todo' => ['todos', 'to-do', 'to-dos', 'checklist'],
                'message' => ['messages', 'chat', 'chats', 'mensahe', 'dm', 'pm'],
                'backup' => ['backups', 'back-up', 'backed'],
                'mention' => ['mentions', 'mentioned', 'tag', 'tagged', 'tagging', 'itag'],
                'done' => ['finished', 'complete', 'completed', 'tapos', 'natapos'],
                'confirm' => ['confirmation', 'confirmed', 'approve', 'approval', 'kumpirmahin'],
                'remind' => ['reminder', 'reminders', 'notification', 'notifications', 'notify', 'paalala', 'popup'],
                'launcher' => ['launch', 'install', 'installer', 'setup', 'bat'],
                'deliberation' => ['deliberate', 'deliberated'],
                'subtract' => ['subtraction', 'bawas', 'ibawas'],
                'snapshot' => ['snapshots'],
                'dark' => ['darkmode', 'night'],
            ],

            'phrases' => [
                'to do' => 'todo',
                'mark as done' => 'markdone',
                'mark done' => 'markdone',
                'mark it done' => 'markdone',
                'dark mode' => 'darkmode',
                'r4 01' => 'r401',
                'r4 02' => 'r402',
                'r4 03' => 'r403',
                'whats up' => 'whatsup',
                'what s up' => 'whatsup',
            ],

            'extend' => [
                'greeting' => ['questions' => ['hello lspbot', 'hi lspbot', 'good afternoon', 'good evening', 'magandang umaga po', 'magandang hapon po', 'hey there', 'musta']],
                'thanks' => ['questions' => ['thanks lspbot', 'maraming salamat', 'salamat ha', 'tenkyu', 'thank u', 'arigato']],
                'goodbye' => ['questions' => ['bye po', 'babay', 'ingat', 'una na ako', 'alis na ako', 'logout na ako', 'see you later']],
                'how_are_you' => ['questions' => ['how are you lspbot', 'kumusta ka po', 'ok ka lang', 'how have you been']],
                'off_topic' => ['questions' => ['another joke', 'one more joke', 'joke naman', 'patawanin mo ako', 'make me laugh', 'isa pang joke', 'say something funny']],
                'compliment' => ['questions' => ['ang talino mo', 'matalino ka', 'you are the best', 'thanks you are great', 'ang bait mo']],
            ],

            'entries' => [
                // ===== Everyday conversation ======================================================
                'help_menu' => [
                    'suggest' => false,
                    'questions' => [
                        'examples', 'give me examples', 'sample questions', 'example questions', 'help menu', 'menu', 'show me examples',
                        'what questions can i ask', 'mga halimbawa', 'halimbawa ng tanong', 'ano ang pwede kong itanong', 'what else can you do',
                    ],
                    'keywords' => ['examples', 'sample questions', 'help menu', 'halimbawa'],
                    'answer' => "Here are things you can ask me:\n"
                        ."Records (with lookup access)\n• How many MS are in Purok 1?\n• How many female Kadiwa in Purok 2a grupo 3?\n• MS per purok / members by age group / birthdays per month\n• List the UWP in Purok 4 (then say “more”)\n• Purok 1 vs Purok 2 / compare male and female MS\n• What percent of MS are female?\n• Which purok has the most MK?\n• Who is the oldest member in Purok 3? / average age of Binhi\n• How many baptized this year? / members born in March / birthdays this month\n• How many records are missing a birthday?\n• Follow up with “how about Purok 2?”, “and females?”, “list them”, “per grupo”\n"
                        ."More ways to ask\n• MS not in Purok 1 / non-MS / members except Binhi\n• How many MS in purok 1 and 2? (total plus each Purok)\n• Members 18 to 30 years old / above 60 / edad 18 pataas\n• Ilan ang MS sa ikalawang purok? / purok one / hw many ms in pruok 1\n• Ask two things at once: “How many MS? How about UWP?”\n"
                        ."Your work\n• What is new? (your summary) / Do I have unread messages?\n• What are my tasks? / overdue tasks / my to-dos\n• Is the monthly session open? / movement codes this month\n"
                        ."How-to\n• How do I assign a task? / How do I @mention someone?\n• Where do I print R4-01? / How do I subtract a member?\n• How do I back up the database? / How do I start LSPDMS with the launcher?\n"
                        ."Everyday\n• What time is it? / What day is today? / Tell me a joke",
                    'answer_tl' => "Ilang halimbawa ng puwede mong itanong:\n• Ilan ang MS sa Purok 1?\n• Ilang babae ang Kadiwa sa Purok 2a?\n• MS per purok / members by age group\n• Ilista ang UWP sa Purok 4 (tapos sabihin ang “more”)\n• Purok 1 vs Purok 2\n• Ilang porsyento ng MS ang babae?\n• Sino ang pinakamatanda sa Purok 3?\n• Ano ang tasks ko? / to-dos ko\n• Bukas ba ang monthly session?\n• Paano mag-assign ng task? / Saan mag-print ng R4-01?\n• Anong oras na? / Anong petsa ngayon?",
                ],
                'whats_up' => [
                    'suggest' => false,
                    'questions' => ['whatsup', 'sup', 'wassup', 'anong ginagawa mo', 'ano ginagawa mo', 'are you there', 'nandyan ka ba', 'andyan ka ba', 'gising ka pa ba', 'hoy', 'uy lspbot'],
                    'keywords' => ['whatsup', 'wassup', 'nandyan ka', 'andyan ka', 'are you there'],
                    'answer' => ['I’m here! Just keeping an eye on the records. What can I do for you?', 'Always here and ready. Ask me anything about LSPDMS!'],
                    'answer_tl' => ['Nandito lang ako! Ano ang maitutulong ko?', 'Gising na gising! Ano ang kailangan mo?'],
                ],
                'bot_feelings' => [
                    'suggest' => false,
                    'questions' => ['i love you', 'mahal kita', 'love you lspbot', 'are you my friend', 'kaibigan ba kita', 'do you like me', 'crush kita'],
                    'keywords' => ['love you', 'mahal kita', 'crush kita', 'my friend'],
                    'answer' => 'Aww, that’s sweet! I’m just a bot, but I’m always happy to help you. 😊',
                    'answer_tl' => 'Ang sweet mo naman! Bot lang ako, pero lagi akong handang tumulong sa iyo. 😊',
                ],
                'mood_bored' => [
                    'suggest' => false,
                    'questions' => ['i am bored', 'im bored', 'nabobored ako', 'boring', 'wala akong magawa', 'inip na ako'],
                    'keywords' => ['bored', 'boring', 'nabobored', 'inip'],
                    'answer' => 'Want a quick win? Ask me “how many records are missing a birthday?” and fill a few in through Inquire / Modify — or ask me for a joke!',
                    'answer_tl' => 'Gusto mo ng mabilis na gawain? Itanong mo “how many records are missing a birthday?” at punan ang ilan sa Inquire / Modify — o humingi ka ng joke!',
                ],
                'mood_hungry' => [
                    'suggest' => false,
                    'questions' => ['i am hungry', 'im hungry', 'gutom na ako', 'nagugutom ako', 'kain tayo', 'kain na', 'lunch na', 'merienda'],
                    'keywords' => ['hungry', 'gutom', 'kain tayo', 'merienda'],
                    'answer' => 'Go grab something to eat — the records will wait for you. Eat well! 🍚',
                    'answer_tl' => 'Kumain ka muna — maghihintay ang records. Kain nang mabuti! 🍚',
                ],
                'mood_happy' => [
                    'suggest' => false,
                    'questions' => ['i am happy', 'im happy', 'masaya ako', 'yay', 'ayos', 'nice', 'great', 'awesome', 'galing', 'astig'],
                    'keywords' => ['masaya ako', 'yay', 'awesome', 'astig'],
                    'answer' => 'That’s great to hear! 😄 Anything else I can help with?',
                    'answer_tl' => 'Ang saya naman! 😄 May iba pa ba akong maitutulong?',
                ],
                'mood_sad' => [
                    'suggest' => false,
                    'questions' => ['i am sad', 'im sad', 'malungkot ako', 'nalulungkot ako', 'sad', 'bad day', 'stressed out'],
                    'keywords' => ['sad', 'malungkot', 'nalulungkot', 'bad day'],
                    'answer' => 'I’m sorry you’re feeling down. Take a breath — you’re doing good work. If something in LSPDMS is giving you trouble, tell me and we’ll fix it together.',
                    'answer_tl' => 'Pasensya na at malungkot ka. Huminga ka muna — maganda ang ginagawa mo. Kung may problema sa LSPDMS, sabihin mo at aayusin natin.',
                ],
                'blessing' => [
                    'suggest' => false,
                    'questions' => ['god bless', 'god bless you', 'pagpalain ka ng diyos', 'ingat ka', 'ingat po', 'take care'],
                    'keywords' => ['god bless', 'pagpalain', 'take care'],
                    'answer' => 'God bless you too! Take care. 🙏',
                    'answer_tl' => 'Pagpalain ka rin ng Diyos! Ingat ka. 🙏',
                ],
                'holiday_greetings' => [
                    'suggest' => false,
                    'questions' => ['merry christmas', 'maligayang pasko', 'happy new year', 'manigong bagong taon', 'happy holidays', 'happy birthday'],
                    'keywords' => ['merry christmas', 'maligayang pasko', 'happy new year', 'bagong taon', 'happy holidays'],
                    'answer' => 'Thank you, and the same to you! 🎉 May your celebration be blessed.',
                    'answer_tl' => 'Salamat, ikaw rin! 🎉 Pagpalain nawa ang inyong pagdiriwang.',
                ],
                'laugh' => [
                    'suggest' => false,
                    'questions' => ['haha', 'hahaha', 'hehe', 'lol', 'lmao', 'xd', 'hihi'],
                    'keywords' => ['haha', 'hahaha', 'hehe', 'lol'],
                    'answer' => ['Glad that made you smile! 😄', 'Haha! Anything else I can do for you?'],
                ],
                'wait' => [
                    'suggest' => false,
                    'questions' => ['wait', 'wait lang', 'sandali', 'sandali lang', 'teka', 'teka lang', 'hold on', 'one moment'],
                    'keywords' => ['sandali', 'teka', 'hold on'],
                    'answer' => 'Take your time — I’ll be right here.',
                    'answer_tl' => 'Sige, maghihintay ako.',
                ],
                'bot_age' => [
                    'suggest' => false,
                    'questions' => ['how old are you', 'ilang taon ka na', 'when were you born', 'kailan ka ginawa'],
                    'keywords' => ['how old are you', 'ilang taon ka'],
                    'not' => ['form', 'forms', 'record', 'records', 'member', 'members', 'snapshot', 'snapshots', 'file', 'files', 'note', 'notes'],
                    'answer' => 'I was born together with LSPDMS 2.0 in 2026, so I’m still pretty young — but I learn new things whenever the system grows!',
                ],
                'bot_offline' => [
                    'suggest' => false,
                    'questions' => [
                        'are you chatgpt', 'are you ai', 'are you an ai', 'do you use the internet', 'is this chatgpt', 'are you gemini',
                        'do you send my data outside', 'is my data safe with you', 'online ka ba',
                    ],
                    'keywords' => ['chatgpt', 'gemini', 'openai', 'use the internet', 'data safe'],
                    'answer' => 'I’m not ChatGPT or any outside AI. I run completely inside LSPDMS on this server, without internet, and I only read the data your account is already allowed to see. Nothing you type is sent anywhere else.',
                    'answer_tl' => 'Hindi ako ChatGPT o anumang AI sa labas. Tumatakbo ako sa loob mismo ng LSPDMS, walang internet, at binabasa ko lang ang data na puwede nang makita ng account mo.',
                ],
                'weather' => [
                    'suggest' => false,
                    'questions' => ['what is the weather', 'whats the weather today', 'umuulan ba', 'is it raining', 'mainit ba ngayon', 'may bagyo ba'],
                    'keywords' => ['weather', 'umuulan', 'bagyo', 'raining'],
                    'answer' => 'I can’t check the weather — I run offline inside LSPDMS. Stay safe, and bring an umbrella just in case! ☔',
                ],

                // ===== Tasks ======================================================================
                'tasks_assign' => [
                    'title' => 'Assigning a task',
                    'route' => 'tasks',
                    'link_label' => 'Open Tasks',
                    'questions' => [
                        'How do I assign a task?', 'How do I create a task?', 'How do I post a new task?', 'How do I give a task to a secretary?',
                        'Paano mag-assign ng task?', 'Paano gumawa ng task?', 'Can I edit or delete a task?', 'How do I set a due date on a task?',
                    ],
                    'keywords' => ['assign task', 'new task', 'create task', 'post task', 'task due date', 'delete task', 'edit task'],
                    'boost' => 0.08,
                    'answer' => 'Open Tasks in the sidebar and press New task (Head Secretary only). Give it a title and details, pick a due date (quick buttons: today, tomorrow, next week — past dates are not allowed), then choose who sees it: only the assigned secretaries, or everyone. For each assigned secretary you can allow commenting and “Mark as done”. Use the ⋯ menu on a task to edit or delete it.',
                    'answer_tl' => 'Buksan ang Tasks sa sidebar at pindutin ang New task (Head Secretary lang). Ilagay ang title at details, pumili ng due date (hindi puwede ang nakaraang petsa), at piliin kung sino ang makakakita: ang mga naka-assign lang o lahat. Puwede mong payagan ang bawat secretary na mag-comment at mag-“Mark as done”. Gamitin ang ⋯ menu para i-edit o i-delete.',
                ],
                'tasks_mentions' => [
                    'title' => '@mentions in Tasks',
                    'route' => 'tasks',
                    'link_label' => 'Open Tasks',
                    'questions' => [
                        'How do I mention someone?', 'How do I tag a secretary in a task?', 'What does @ do in tasks?', 'Paano mag-mention?',
                        'Paano i-tag ang secretary sa comment?', 'Why did I get a mentioned you notification?', 'Who can I mention?',
                    ],
                    'keywords' => ['mention', '@mention', 'tag', 'mentioned you'],
                    'boost' => 0.08,
                    'answer' => 'Type @ in a task’s title, details or a comment and pick a name from the list (for example @KimberlyOstil). Only people who can see that task are offered. The person gets a notification and the task shows a “Mentioned you” tag for them. Mentions are saved, so re-editing a task does not notify people twice.',
                    'answer_tl' => 'Mag-type ng @ sa title, details o comment ng task at pumili ng pangalan sa listahan. Ang mga makakakita lang ng task ang lalabas. Makakatanggap ng notification ang na-mention at may “Mentioned you” na tag sa task.',
                ],
                'tasks_mark_done' => [
                    'title' => 'Marking a task as done',
                    'route' => 'tasks',
                    'link_label' => 'Open Tasks',
                    'questions' => [
                        'How do I mark a task as done?', 'How do I finish a task?', 'Paano i-mark as done ang task?', 'I marked it done by mistake',
                        'How do I undo mark as done?', 'Who confirms a task?', 'How does the Head confirm a task?', 'How do I reopen a task?',
                        'Why is my task waiting for confirmation?',
                    ],
                    'keywords' => ['markdone', 'finish task', 'undo done', 'confirm task', 'reopen task', 'waiting for confirmation', 'task done'],
                    'boost' => 0.1,
                    'answer' => 'Each assigned secretary presses “Mark as done” for their own part of the task (you can Undo while it waits). When everyone is done, the task waits for the Head Secretary, who presses Confirm to close it — it then moves to the Completed tab with an on-time or late badge. The Head can also Reopen a task that needs more work.',
                    'answer_tl' => 'Bawat naka-assign na secretary ay pipindot ng “Mark as done” para sa sarili niyang bahagi (puwedeng i-Undo habang naghihintay). Kapag tapos na lahat, kukumpirmahin ito ng Head Secretary (Confirm) at mapupunta sa Completed tab. Puwede ring i-Reopen ng Head ang task.',
                ],
                'tasks_reminders' => [
                    'title' => 'Task reminders',
                    'route' => 'tasks',
                    'link_label' => 'Open Tasks',
                    'questions' => [
                        'Why do I keep getting a task pop-up?', 'How do task reminders work?', 'What is the number on Tasks in the sidebar?',
                        'Will I be reminded before a task is due?', 'Paano mawala ang task reminder?', 'Bakit may pop-up na task?',
                    ],
                    'keywords' => ['task reminder', 'task popup', 'due soon', 'tasks badge', 'task notification'],
                    'boost' => 0.08,
                    'answer' => 'New tasks show a pop-up reminder and a number on Tasks in the sidebar until you open the Tasks page. Tasks due within two days that you still need to finish show a “due soon” reminder. For the Head Secretary, tasks that secretaries marked done count as new until you look at them.',
                    'answer_tl' => 'May pop-up at numero sa Tasks sa sidebar ang bagong tasks hangga’t hindi mo binubuksan ang Tasks page. May “due soon” na paalala para sa tasks na due sa loob ng dalawang araw.',
                ],
                'tasks_comments' => [
                    'title' => 'Commenting on tasks',
                    'route' => 'tasks',
                    'link_label' => 'Open Tasks',
                    'questions' => ['How do I comment on a task?', 'Can I reply to a comment?', 'How do I react to a task?', 'Paano mag-comment sa task?', 'Where are completed tasks?'],
                    'keywords' => ['task comment', 'comment on task', 'reply comment', 'react', 'completed tasks'],
                    'boost' => 0.05,
                    'answer' => 'Open the task in Tasks and write in the comment box (if the Head allowed you to comment). You can reply to a comment and add a reaction. Finished, confirmed tasks are in the Completed tab.',
                ],

                'tasks_calendar' => [
                    'title' => 'Task calendar and due dates',
                    'route' => 'tasks',
                    'link_label' => 'Open Tasks',
                    'questions' => [
                        'Where is the task calendar?', 'How do I see tasks by due date?', 'Where can I see which tasks are due this week?',
                        'Saan makikita ang due date ng tasks?', 'What does the red task mean?', 'Which tasks are overdue?',
                    ],
                    'keywords' => ['task calendar', 'due date calendar', 'calendar', 'due this week'],
                    'boost' => 0.06,
                    'answer' => 'Open Tasks: the side card shows a due-date calendar, and each task shows when it is due (due today, tomorrow, or overdue in red). Tasks due within two days that still need your part show a “due soon” reminder. Ask me “what are my tasks?” or “overdue tasks” for a live list.',
                    'answer_tl' => 'Buksan ang Tasks: may calendar ng due dates sa gilid, at makikita sa bawat task kung kailan ito due (pula kapag overdue). Itanong mo sa akin ang “tasks ko” para sa listahan.',
                ],
                'tasks_overview' => [
                    'title' => 'How Tasks work',
                    'route' => 'tasks',
                    'link_label' => 'Open Tasks',
                    'questions' => [
                        'How do tasks work?', 'What is the tasks menu?', 'Explain the task flow', 'Paano gumagana ang tasks?',
                        'Who can see a task?', 'What is the difference between tasks and to-dos?',
                    ],
                    'keywords' => ['tasks work', 'task flow', 'tasks menu', 'tasks and todo'],
                    'boost' => 0.05,
                    'answer' => "Tasks, step by step:\n1. The Head Secretary posts a task (title, details, due date) and picks the assignees and who can see it.\n2. Anyone can be @mentioned in the task or its comments and gets a notification.\n3. Every assignee presses “Mark as done” for their own part.\n4. When all parts are done, the Head Secretary confirms it and it moves to Completed (on time or late).\nTo-dos are different: they are your private checklist in the chat box, which nobody else sees.",
                    'answer_tl' => "Ganito ang Tasks:\n1. Magpo-post ang Head Secretary ng task at pipili ng mga naka-assign.\n2. Puwedeng i-@mention ang sinuman sa task o comment.\n3. Bawat naka-assign ay magma-“Mark as done” ng sariling bahagi.\n4. Kapag tapos na lahat, kukumpirmahin ito ng Head Secretary.\nAng To-do naman ay pribadong checklist mo sa chat box.",
                ],

                // ===== Messages and To-Do =========================================================
                'messages' => [
                    'title' => 'Messages',
                    'route' => 'messages.index',
                    'link_label' => 'Open Messages',
                    'questions' => [
                        'How do I message another secretary?', 'Where are my messages?', 'How do I send a picture?', 'Paano mag-message?',
                        'Saan ang chat?', 'Can I send attachments?', 'Do messages get deleted?', 'How do I know if my message was read?',
                    ],
                    'keywords' => ['message', 'send picture', 'attachment', 'chat box', 'read receipt', 'seen'],
                    'not' => ['todo', 'task'],
                    'answer' => 'Click the round chat button at the bottom-right of any page (or open Messages), pick a secretary and type. Use ＋ to attach an image (JPG, PNG, GIF or WebP). You’ll see when a message is delivered and read, and when the other person is typing. Chats are private to the two of you and are removed automatically after one year.',
                    'answer_tl' => 'I-click ang bilog na chat button sa kanang ibaba ng page (o buksan ang Messages), pumili ng secretary at mag-type. Gamitin ang ＋ para mag-attach ng larawan. Makikita mo kung na-deliver at nabasa na. Pribado ang chat sa inyong dalawa at awtomatikong nabubura pagkalipas ng isang taon.',
                ],
                'todo' => [
                    'title' => 'Your To-Do list',
                    'route' => 'messages.index',
                    'link_label' => 'Open Messages',
                    'questions' => [
                        'Where is my to-do list?', 'How do I add a to-do?', 'How do I make a personal checklist?', 'Paano magdagdag ng to-do?',
                        'Can others see my to-do?', 'How do I finish a to-do?', 'Saan ang to-do?',
                    ],
                    'keywords' => ['todo', 'todo list', 'checklist', 'personal list'],
                    'boost' => 0.1,
                    'answer' => 'Your To-Do list is in the chat box: click the chat button at the bottom-right, then the To-Do tab. Type a to-do, optionally pick a due date, and press Add. Only you can see it. Click a to-do to edit it and tick it when done. Ask me “what are my to-dos?” anytime.',
                    'answer_tl' => 'Nasa chat box ang To-Do list mo: i-click ang chat button sa kanang ibaba, saka ang To-Do tab. Mag-type, pumili ng due date kung gusto, at pindutin ang Add. Ikaw lang ang makakakita nito.',
                ],

                // ===== Records, forms and tools ===================================================
                'dashboard_details' => [
                    'title' => 'Dashboard charts',
                    'feature' => 'dashboard_allow',
                    'route' => 'dashboard',
                    'link_label' => 'Open Dashboard',
                    'questions' => [
                        'What does the dashboard show?', 'Where are the records over time?', 'Where can I see new baptisees?', 'Where do I see duplicate registry numbers?',
                        'Where is the registry updating monitoring?', 'Where are the monthly movement codes?', 'Where is the CFO group breakdown?',
                    ],
                    'keywords' => ['records over time', 'new baptisees', 'duplicate registry', 'registry updating', 'movement codes chart', 'cfo group'],
                    'answer' => 'Dashboard shows active records by Purok and gender, the CFO group breakdown, Records over time (monthly MS/UWP/MK snapshots — click a point for its movement codes), New baptisees, Registry updating monitoring (blank fields and RFID registries count as missing; click a count to see and fill the records) and Duplicate registry numbers.',
                ],
                'status_meanings' => [
                    'title' => 'Record statuses',
                    'questions' => [
                        'What are the statuses?', 'What is the difference between MS UWP and MK?', 'What does INACTIVE mean?', 'Ano ang kalagayan?',
                        'What statuses are there?', 'What are active records?',
                    ],
                    'keywords' => ['statuses', 'kalagayan', 'difference between ms', 'what does inactive mean', 'active records mean'],
                    'answer' => 'Each record has a status (kalagayan): MS, UWP and MK are the active statuses, and INACTIVE records are no longer counted (for example after a Subtract or a De-Listment). MK and R2-10 details are confidential and need De-Listment access to view by name. When I count “members” or “active records”, I count MS + UWP + MK.',
                ],
                'kapisanan_codes' => [
                    'title' => 'Kapisanan and gender codes',
                    'questions' => ['What does B and K mean in kapisanan?', 'What are the kapisanan codes?', 'What does L and B mean in gender?', 'Ano ang ibig sabihin ng B at K?'],
                    'keywords' => ['kapisanan codes', 'gender codes', 'b and k'],
                    'answer' => 'Kapisanan is stored as B = Buklod, K = Kadiwa, or Binhi. Gender is stored as L = Lalaki (male) and B = Babae (female). Buklod records are treated as married.',
                ],
                'movement_codes' => [
                    'title' => 'Monthly movement codes',
                    'feature' => 'form_generator_allow',
                    'route' => 'forms.monthly.session',
                    'link_label' => 'Open Monthly Session',
                    'questions' => ['What do the monthly codes mean?', 'What is C+ and C-?', 'What is a movement code?', 'Ano ang ibig sabihin ng C+?', 'What are the movement codes?'],
                    'keywords' => ['monthly code', 'movement code', 'c+', 'c-'],
                    'answer' => 'Every Monthly Session movement gets a code such as “MS: B” or “UWP: C+”. In the Resumen, A is the starting count (last month’s closing MS/UWP total); B and C+ are added (B a new member, C+ a transfer in); C−, D, DD, E, G and H are deducted (C− a transfer out; D, DD, G and H are the Subtract reasons, after which the record becomes INACTIVE). The codes feed the Resumen, R4-01 and the Dashboard movement chart. Ask me “movement codes this month” for the live count.',
                ],
                'deliberation_add' => [
                    'title' => 'Adding a member (Deliberation Form)',
                    'feature' => 'form_generator_allow',
                    'route' => 'forms.monthly.deliberation',
                    'link_label' => 'Open Deliberation Form',
                    'questions' => [
                        'How do I add a new member?', 'How do I add a new record?', 'Where do I add a new baptized member?', 'Paano magdagdag ng bagong member?',
                        'How do I add a transfer in?', 'Where is the Deliberation Form?',
                    ],
                    'keywords' => ['add new member', 'add new record', 'deliberation form', 'add member', 'transfer in'],
                    'not' => ['mk', 'note', 'todo', 'task'],
                    'boost' => 0.05,
                    'answer' => 'Go to Form Generator → Deliberation Form → Add during an active Monthly Session. Fill in the member’s details and movement code; LSPDMS checks the registry number and suggests possible duplicates before saving. The movement then appears in the Monthly Session list, where it can be undone until the session is finished.',
                    'answer_tl' => 'Pumunta sa Form Generator → Deliberation Form → Add habang may active na Monthly Session. Ilagay ang detalye at movement code; susuriin ng LSPDMS ang registry number at mga posibleng duplicate bago i-save.',
                ],
                'deliberation_subtract' => [
                    'title' => 'Subtracting a member',
                    'feature' => 'form_generator_allow',
                    'route' => 'forms.monthly.deliberation',
                    'link_label' => 'Open Deliberation Form',
                    'questions' => ['How do I subtract a member?', 'How do I remove a member who transferred out?', 'Paano mag-subtract?', 'How do I mark a member as inactive?', 'Where is Subtract?'],
                    'keywords' => ['subtract', 'transfer out', 'make inactive', 'remove member'],
                    'not' => ['endorsed', 'r210'],
                    'answer' => 'Go to Form Generator → Deliberation Form → Subtract during an active Monthly Session, search the member, choose the reason/code and save. The record becomes INACTIVE when the session is finished. R2-10 endorsed records cannot be subtracted — they leave through De-Listment instead.',
                    'answer_tl' => 'Pumunta sa Form Generator → Deliberation Form → Subtract habang may active na Monthly Session, hanapin ang member, piliin ang code at i-save. Hindi puwedeng i-subtract ang R2-10 endorsed — sa De-Listment sila dumadaan.',
                ],
                'deliberation_modify' => [
                    'title' => 'Modifying a member in Monthly Session',
                    'feature' => 'form_generator_allow',
                    'route' => 'forms.monthly.modify',
                    'link_label' => 'Open Deliberation Modify',
                    'questions' => ['How do I transfer a member to another purok?', 'How do I change a member’s status in the monthly session?', 'Where is Deliberation Modify?', 'Paano ilipat ng purok ang member?'],
                    'keywords' => ['transfer to another purok', 'change purok', 'deliberation modify', 'ilipat'],
                    'answer' => 'Go to Form Generator → Deliberation Form → Modify during an active Monthly Session. Search the member and change the Purok/Grupo (a transfer, coded C− / C+) or the status. Each change is recorded with its movement code.',
                ],
                'monthly_session_monitor' => [
                    'title' => 'Monthly Session page',
                    'feature' => 'form_generator_allow',
                    'route' => 'forms.monthly.session',
                    'link_label' => 'Open Monthly Session',
                    'questions' => [
                        'How do I start a monthly session?', 'How do I finish the monthly session?', 'How do I undo a movement?', 'Where is the Monthly Session page?',
                        'Paano simulan ang monthly session?', 'Paano tapusin ang monthly session?',
                    ],
                    'keywords' => ['start monthly session', 'finish monthly session', 'undo movement', 'session monitor'],
                    'boost' => 0.05,
                    'answer' => 'Open Form Generator → Monthly Session. Press Start (from the 15th it is for the next month; one session per month). Add, Subtract and Modify movements are listed there and can be undone one by one. Press Finish when done: the records are updated, the movement codes are saved and an R4-01 snapshot is kept.',
                ],
                'r401' => [
                    'title' => 'R4-01 and Resumen',
                    'feature' => 'form_generator_allow',
                    'route' => 'forms.monthly.generators',
                    'link_label' => 'Open Resumen & R4-01',
                    'questions' => ['Where are the R4-01 snapshots?', 'How do I download the R4-01?', 'How do I make the Resumen?', 'Where is the old R4-01?', 'Paano i-download ang Resumen?'],
                    'keywords' => ['r401', 'resumen', 'r4-01 snapshot', 'snapshot'],
                    'boost' => 0.05,
                    'answer' => 'Form Generator → Generate Forms → Monthly: “Resumen & R4-01 (This Month)” builds and downloads this month’s Resumen and R4-01 (Word). “R4-01 Snapshots” keeps the R4-01 saved when each Monthly Session was finished — view or download them there (the Head Secretary can delete a snapshot).',
                ],
                'r402_r403' => [
                    'title' => 'R4-02 and R4-03 weekly forms',
                    'feature' => 'form_generator_allow',
                    'route' => 'forms.weekly.r4-02',
                    'link_label' => 'Open R4-02 Generator',
                    'questions' => ['How do I make the R4-02?', 'How do I print R4-03?', 'Where is the weekly form?', 'Paano gumawa ng R4-02?', 'What is a hakbang?'],
                    'keywords' => ['r402', 'r403', 'weekly form', 'hakbang'],
                    'boost' => 0.05,
                    'answer' => 'Form Generator → Generate Forms → Weekly: open R4-02 Generator, choose the Purok and members and the hakbang, then Generate and Print. R4-03 Generator prints the R4-03. The hakbang list is managed by the Head Secretary in Superadmin Dashboard → R4-02 Hakbang.',
                ],
                'delistment_tools' => [
                    'title' => 'De-Listment exports and dates',
                    'feature' => 'Delistment_allow',
                    'route' => 'de-listment',
                    'link_label' => 'Open De-Listment',
                    'questions' => ['How do I export the R2-10 list?', 'How do I download the de-listment history?', 'How do I update baptism dates in De-Listment?', 'How do I add an MK?'],
                    'keywords' => ['export r210', 'delistment history', 'download history', 'update baptism dates'],
                    'answer' => 'In De-Listment Portal, click a chart category (eligible, not eligible, endorsed, MK, progress…) to export that list as Word, use Download history for the De-Listment history, fill missing baptism dates in the table and save, and use Add MK to add an MK record. Endorse eligible records first; an endorsed MK can then be delisted (it becomes INACTIVE).',
                ],
                'bnh_details' => [
                    'title' => 'Filter & BNH Helper details',
                    'feature' => 'filter_bnh_helper_allow',
                    'route' => 'filter-bnh-helper',
                    'link_label' => 'Open Filter & BNH Helper',
                    'questions' => ['How do I use the BNH form helper?', 'How do I download the BNH word file?', 'How do I reset the BNH checks?', 'What is the Purok Group Filter?'],
                    'keywords' => ['bnh form helper', 'bnh word', 'reset bnh', 'purok group filter'],
                    'answer' => 'Filter & BNH Helper has the Purok Group Filter, the BNH Form Helper (tick each member’s checks) and its Resumen; download both as Word. Only the Head Secretary can reset all BNH checks, from Superadmin Dashboard → BNH form reset with a one-time code.',
                ],

                'note_checker_details' => [
                    'title' => 'Note Checker',
                    'feature' => 'note_checker_allow',
                    'route' => 'note-checker',
                    'link_label' => 'Open Note Checker',
                    'questions' => [
                        'How do I add a note to a record?', 'How do I delete a note?', 'What date format do notes use?', 'Why is the note date different?',
                        'Paano magdagdag ng note?', 'Where are the record notes?',
                    ],
                    'keywords' => ['add note', 'delete note', 'note date', 'record notes'],
                    'boost' => 0.05,
                    'answer' => 'Note Checker (sidebar → Tools): search a name or registry number, pick the record, then Add note or delete one. Each new note starts with today’s date (day/month/year). Older notes may show their date as month/day/year, and Monthly Session log entries use day-month-year — all of them are read correctly.',
                ],
                'records_inquire' => [
                    'title' => 'Inquire / Modify and the Smart Helper',
                    'feature' => 'Inquire_Allow',
                    'route' => 'inquire-modify',
                    'link_label' => 'Open Inquire / Modify',
                    'questions' => [
                        'Where do I search all records?', 'How do I see the full list of members?', 'How do I edit a record?', 'How do I delete a record?',
                        'What is the Smart Helper?', 'Paano mag-edit ng record?', 'Where is Inquire Modify?',
                    ],
                    'keywords' => ['inquire', 'inquire modify', 'full list', 'search records', 'delete record', 'smarthelper'],
                    'boost' => 0.04,
                    'answer' => 'Inquire / Modify (sidebar → Records) is the full records table: search and filter by name, status, Purok and more, open a record to edit it, and use the Smart Helper to spot similar names that may be duplicates. Deleting is limited (records that are R2-10 endorsed or part of history are protected). I can count and list records for you here, but changes are only made on that page.',
                ],
                'pages_overview' => [
                    'title' => 'Pages in LSPDMS',
                    'questions' => [
                        'What pages are there?', 'What is in the sidebar?', 'What are the features of LSPDMS?', 'Give me a tour', 'What is LSPDMS?',
                        'Ano ang mga features ng LSPDMS?', 'Ano ang laman ng sidebar?', 'What can LSPDMS do?',
                    ],
                    'keywords' => ['features of lspdms', 'what is lspdms', 'tour', 'sidebar', 'what pages'],
                    'answer' => "LSPDMS is the records system for the Lupon sa Pagpapatibay secretaries. The sidebar has:\n• Overview: Home, Dashboard (charts), Tasks\n• Records: Inquire / Modify, De-Listment (R2-10 and MK)\n• Tools: Note Checker, Tarheta Status Checker, Filter & BNH Helper\n• Form Generator: Weekly (R4-02, R4-03), Monthly (Resumen & R4-01, R4-01 Snapshots), Deliberation (Add, Subtract, Modify) and Monthly Session\n• Administration (Head Secretary): Superadmin Dashboard\nThe round chat button opens Messages, your To-Do list and me. Pages you lack access to are hidden.",
                    'answer_tl' => "Ang LSPDMS ang records system ng mga secretary. Nasa sidebar ang: Home, Dashboard, Tasks; Inquire / Modify at De-Listment; Note Checker, Tarheta Status Checker, Filter & BNH Helper; Form Generator (R4-02, R4-03, Resumen, R4-01, Deliberation, Monthly Session); at Superadmin Dashboard para sa Head Secretary. Nasa chat button ang Messages, To-Do at ako.",
                ],
                'monthly_session_flow' => [
                    'title' => 'Monthly Session step by step',
                    'feature' => 'form_generator_allow',
                    'route' => 'forms.monthly.session',
                    'link_label' => 'Open Monthly Session',
                    'questions' => [
                        'Explain the monthly session', 'What are the steps of the monthly session?', 'Monthly session step by step',
                        'Ano ang proseso ng monthly session?', 'What happens when I finish the session?', 'What is a snapshot?',
                    ],
                    'keywords' => ['monthly session steps', 'proseso', 'step by step', 'finish session', 'what is a snapshot'],
                    'boost' => 0.06,
                    'answer' => "Monthly Session, step by step:\n1. Form Generator → Monthly Session → Start (from the 15th it is for next month; one per month).\n2. Record movements: Deliberation → Add (new member, transfer in), Subtract (D, DD, G, H) or Modify (transfer C−/C+ or status change).\n3. Review the list; any movement can be undone until you finish.\n4. Finish: records are updated, codes are saved to history, and an R4-01 snapshot is kept for that month.\nAsk me “is the monthly session open?” for the live state.",
                ],
                'it_recovery' => [
                    'title' => 'IT recovery',
                    'questions' => [
                        'What is IT recovery?', 'The head secretary is locked out', 'Nobody can sign in because email is down', 'What is break glass?',
                        'Paano kung naka-lock ang Head Secretary?',
                    ],
                    'keywords' => ['it recovery', 'break glass', 'locked out', 'email is down'],
                    'answer' => 'IT recovery is a separate, password-only sign-in for the IT person, meant for emergencies such as email being down. It can only turn off email-dependent sign-in features, reset an account password, or re-enable an account, and every action is written to its own audit log. It never touches records. Ask your IT contact for the address and credentials.',
                ],

                // ===== Everyday conversation, part 2 =============================================
                'bot_sleep' => [
                    'suggest' => false,
                    'questions' => ['do you sleep', 'natutulog ka ba', 'are you tired', 'pagod ka na ba', 'do you eat', 'kumakain ka ba'],
                    'keywords' => ['do you sleep', 'natutulog ka', 'pagod ka', 'do you eat'],
                    'answer' => 'I never sleep or get tired — I’m here whenever LSPDMS is running. You should rest though! 😴',
                    'answer_tl' => 'Hindi ako natutulog o napapagod — nandito ako hangga’t bukas ang LSPDMS. Ikaw, magpahinga ka rin! 😴',
                ],
                'bot_favorite' => [
                    'suggest' => false,
                    'questions' => ['what is your favorite', 'whats your favorite color', 'what is your favorite food', 'anong paborito mo', 'do you have a favorite'],
                    'keywords' => ['favorite', 'paborito'],
                    'answer' => 'My favorite thing is a record with no missing data! 😄 Ask me “how many records are missing a birthday?” and we can work on that together.',
                    'answer_tl' => 'Paborito ko ang record na walang kulang na data! 😄 Itanong mo “how many records are missing a birthday?” at sabay nating ayusin.',
                ],
                'bot_language' => [
                    'suggest' => false,
                    'questions' => ['do you speak tagalog', 'marunong ka ba mag tagalog', 'can you speak filipino', 'english or tagalog', 'pwede ba taglish'],
                    'keywords' => ['speak tagalog', 'mag tagalog', 'speak filipino', 'taglish'],
                    'answer' => 'Yes! You can ask me in English, Tagalog or Taglish — for example “ilan ang MS sa Purok 1?” or “how many babae sa Kadiwa?”.',
                    'answer_tl' => 'Oo naman! Puwede kang magtanong sa English, Tagalog o Taglish — halimbawa “ilan ang MS sa Purok 1?”.',
                ],
                'motivation' => [
                    'suggest' => false,
                    'questions' => ['motivate me', 'i need motivation', 'pampalakas loob', 'encourage me', 'i am tired', 'pagod na ako', 'ang dami kong gagawin'],
                    'keywords' => ['motivate', 'motivation', 'pagod na ako', 'encourage me', 'dami kong gagawin'],
                    'answer' => ['One record at a time — every entry you fix helps the whole locale. You’ve got this! 💪 Ask me “what is new?” to see what needs you first.', 'Take a short break, drink some water, then tackle the smallest task first. Small wins add up! 💪'],
                    'answer_tl' => ['Isa-isa lang — bawat record na naaayos mo ay tulong sa buong lokal. Kaya mo ’yan! 💪 Itanong mo “ano ang bago?” para makita kung ano ang uunahin.', 'Magpahinga sandali, uminom ng tubig, tapos unahin ang pinakamaliit na gawain. Kaya mo ’yan! 💪'],
                ],

                // ===== Administration (Head Secretary) ============================================
                'backups' => [
                    'title' => 'Database backups',
                    'feature' => 'head_secretary',
                    'route' => 'superadmin-dashboard',
                    'link_label' => 'Open Superadmin Dashboard',
                    'questions' => [
                        'How do I back up the database?', 'Where are the backups?', 'How do I make a backup?', 'Is there an automatic backup?',
                        'Paano mag-backup?', 'How do I restore a backup?', 'How do I download a backup?',
                    ],
                    'keywords' => ['backup', 'restore', 'database backup', 'automatic backup'],
                    'boost' => 0.08,
                    'answer' => 'Superadmin Dashboard → Database backups (Head Secretary). LSPDMS makes a backup automatically every month and right before every database import; you can also add your own .sql export as a manual backup, and download or rename any backup (each is checked with SHA-256). To restore, import a backup with Database configuration → Import Database.',
                    'answer_tl' => 'Superadmin Dashboard → Database backups (Head Secretary). May awtomatikong backup buwan-buwan at bago ang bawat import; puwede ka ring magdagdag ng sariling backup at i-download o i-rename ito. Para i-restore, i-import ang backup sa Import Database.',
                ],
                'database_export' => [
                    'title' => 'Exporting the database',
                    'feature' => 'head_secretary',
                    'route' => 'superadmin-dashboard',
                    'link_label' => 'Open Superadmin Dashboard',
                    'questions' => ['How do I export the database?', 'Can I export to Excel?', 'How do I download the whole database?', 'Paano i-export ang database?'],
                    'keywords' => ['export database', 'export sql', 'export excel', 'xlsx'],
                    'answer' => 'Superadmin Dashboard → Database configuration → Export Database downloads an LSPDMS .sql file. Optional Excel table export saves chosen tables as .xlsx (passwords are never exported).',
                ],
                'email_delivery' => [
                    'title' => 'Email delivery (Gmail)',
                    'feature' => 'head_secretary',
                    'route' => 'superadmin-dashboard',
                    'link_label' => 'Open Superadmin Dashboard',
                    'questions' => ['How do I connect Gmail?', 'Why are verification codes not sent?', 'How do I set up email?', 'Where is SMTP?'],
                    'keywords' => ['connect gmail', 'smtp', 'email delivery', 'codes not sent'],
                    'answer' => 'Superadmin Dashboard → Email delivery: connect the one Gmail account LSPDMS uses to send sign-in codes and password-recovery links, then verify it with the code it sends. Without it, Multi Auth and Forgot Password emails cannot be sent.',
                ],
                'transfer_head' => [
                    'title' => 'Transferring the Head Secretary role',
                    'feature' => 'head_secretary',
                    'route' => 'superadmin-dashboard',
                    'link_label' => 'Open Superadmin Dashboard',
                    'questions' => ['How do I transfer the Head Secretary role?', 'How do I make someone Head Secretary?', 'Paano ilipat ang Head Secretary?'],
                    'keywords' => ['transfer head', 'new head secretary', 'transfer role'],
                    'answer' => 'Superadmin Dashboard → Secretaries management → Transfer Head Secretary role. There can be only one Head Secretary; after the transfer your account becomes a Secretary.',
                ],
                'chatbot_permission' => [
                    'title' => 'Allowing chatbot record lookups',
                    'feature' => 'head_secretary',
                    'route' => 'superadmin-dashboard',
                    'link_label' => 'Open Superadmin Dashboard',
                    'questions' => ['How do I let a secretary use LSPBot record lookups?', 'How do I enable chatbot record lookups?', 'Why can’t LSPBot count records for me?'],
                    'keywords' => ['chatbot record lookups', 'chatbot permission', 'allow chatbot'],
                    'answer' => 'Superadmin Dashboard → Secretaries → Secretary Permissions → tick “Allow chatbot record lookups” for that secretary. MK names and R2-10 details also need De-Listment access, and Tarheta/filing questions need Tarheta Status Checker access.',
                ],

                // ===== Getting in and running LSPDMS ==============================================
                'launcher' => [
                    'title' => 'LSPDMS Launcher',
                    'questions' => [
                        'How do I start LSPDMS?', 'How do I install LSPDMS on a new computer?', 'What is the LSPDMS Launcher?', 'How do I update LSPDMS?',
                        'How do I stop the server?', 'Paano buksan ang LSPDMS?', 'Paano i-install ang LSPDMS?', 'The site is not opening',
                    ],
                    'keywords' => ['launcher', 'start lspdms', 'install lspdms', 'update lspdms', 'stop server'],
                    'boost' => 0.05,
                    'answer' => 'Double-click “LSPDMS Launcher.bat” on the Windows PC with Laragon. It checks the PC, downloads or updates LSPDMS, prepares the settings and database, builds the pages, can open access for other devices on the same Wi-Fi, and then opens LSPDMS at http://localhost:8000. Run it with “stop” to stop the servers. Other devices on the LAN open http://<that PC’s IP>:8000.',
                    'answer_tl' => 'I-double-click ang “LSPDMS Launcher.bat” sa Windows PC na may Laragon. Susuriin nito ang PC, ida-download o ia-update ang LSPDMS, ihahanda ang database, at bubuksan ang LSPDMS sa http://localhost:8000. Sa ibang device sa parehong Wi-Fi, buksan ang http://<IP ng PC>:8000.',
                ],
                'dark_mode' => [
                    'title' => 'Dark mode',
                    'questions' => ['How do I turn on dark mode?', 'How do I change the theme?', 'Paano mag-dark mode?', 'The screen is too bright'],
                    'keywords' => ['darkmode', 'theme', 'too bright'],
                    'answer' => 'Click your name at the top-right and flip the Dark mode switch. Your choice is saved to your account.',
                ],
                'logout' => [
                    'title' => 'Logging out',
                    'questions' => ['How do I log out?', 'How do I sign out?', 'Paano mag-logout?', 'Where is logout?'],
                    'keywords' => ['log out', 'logout', 'sign out'],
                    'answer' => 'Click your name at the top-right and choose Log out.',
                ],
                'forgot_password' => [
                    'title' => 'Forgot password',
                    'route' => 'password.request',
                    'link_label' => 'Open Forgot password',
                    'questions' => ['I forgot my password', 'How do I reset my password?', 'Nakalimutan ko ang password ko', 'Paano i-reset ang password?'],
                    'keywords' => ['forgot password', 'reset password', 'nakalimutan'],
                    'boost' => 0.08,
                    'answer' => 'On the sign-in page, use Forgot password to get a reset link by email (it must be enabled by the Head Secretary and email delivery must be connected). Otherwise ask the Head Secretary to help you.',
                ],
                'verification_code' => [
                    'title' => 'Sign-in verification code',
                    'questions' => ['Where is my verification code?', 'I did not receive the code', 'How do I resend the code?', 'Bakit may code sa login?'],
                    'keywords' => ['verification code', 'resend code', 'sign in code', 'did not receive the code'],
                    'answer' => 'When Multi Auth is on, LSPDMS emails a code to your Gmail after you enter your password. Check Spam/Promotions, then use Resend on the code page. If codes never arrive, the Head Secretary should check Superadmin Dashboard → Email delivery.',
                ],
                'register_account' => [
                    'title' => 'Creating an account',
                    'route' => 'register',
                    'link_label' => 'Open Register',
                    'questions' => ['How do I create an account?', 'How do I register a new secretary?', 'Paano gumawa ng account?', 'Why can’t my new account sign in?'],
                    'keywords' => ['register', 'create account', 'new account', 'sign up'],
                    'answer' => 'New secretaries register from the Register page with their Gmail. The Head Secretary then verifies the account and sets its Tungkulin and permissions in Superadmin Dashboard → Secretaries before it can be used.',
                ],
            ],
        ];
    }

    /** Config merged with this class; config wins where both define the same entry. */
    public static function merge(array $config): array
    {
        $extra = self::all();
        $config['greeting_words'] = array_values(array_unique(array_merge($config['greeting_words'] ?? [], $extra['greeting_words'])));
        foreach ($extra['synonyms'] as $canonical => $variants) {
            $config['synonyms'][$canonical] = array_values(array_unique(array_merge($config['synonyms'][$canonical] ?? [], $variants)));
        }
        $config['phrases'] = ($config['phrases'] ?? []) + $extra['phrases'];
        foreach ($extra['extend'] as $key => $more) {
            if (! isset($config['entries'][$key])) {
                continue;
            }
            foreach (['questions', 'keywords'] as $field) {
                $config['entries'][$key][$field] = array_values(array_unique(array_merge($config['entries'][$key][$field] ?? [], $more[$field] ?? [])));
            }
        }
        $config['entries'] = ($config['entries'] ?? []) + $extra['entries'];

        return $config;
    }
}
