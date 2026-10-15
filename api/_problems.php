<?php
// All challenges live here. Add or edit a challenge in this one file.
// Key = lowercase id (letters, numbers, spaces). Each needs: title, desc, points, answer.
return [

  'cryptography' => [
    '13 caesar'                => ['title' => '13 Caesar',                'desc' => 'Caesar never thought 13 steps ahead.', 'points' => 15, 'files' => ['Cryptography/13 Caesar.txt'], 'answer' => 'psuCTF{this_1s_th3_fl4g}'],
    'apol'                     => ['title' => 'apol',                     'desc' => 'A name behind the machine.', 'points' => 15, 'files' => ['Cryptography/apol.txt'], 'answer' => 'psuCTF{wozniak}'],
    'esrever'                  => ['title' => 'esrever',                  'desc' => 'Read between the lines — backwards.', 'points' => 25, 'files' => ['Cryptography/esrever.txt'], 'answer' => 'psuCTF{an0_d4w}'],
    'combination mo ang limit' => ['title' => 'COMBINATION MO ANG LIMIT', 'desc' => 'The combinations are endless. Or are they?', 'points' => 35, 'files' => ['Cryptography/COMBINATION MO ANG LIMIT.txt'], 'answer' => 'psuCTF{w0w_4nG_g4L!nG}'],
  ],

  // Flags are case-sensitive: FLAG{...}
  'forensics' => [
    'obvious strings'    => ['title' => 'Obvious Strings',       'desc' => 'File: f+controls_logs.txt. A file full of trash information. Sometimes the answer is right in front of you.', 'points' => 5, 'files' => ['forensics/ez_challenges!!!!!/f+ctrl_logs.txt'], 'answer' => 'psuCTF{ctrl_f_master}'],
    'hidden extension'   => ['title' => 'Hidden File Extension', 'desc' => 'File: flag.jpg. A file may not always be what it appears to be. Investigate the file type carefully.', 'points' => 5, 'files' => ['forensics/ez_challenges!!!!!/flag.jpg'], 'answer' => 'psuCTF{ang_pogi_ni_charles}'],
    'metadata'           => ['title' => 'Metadata Never Lies',   'desc' => 'File: meta_yarn.jpg. Images often contain more than what you see on the surface. The answer may be stored in information most people forget exists.', 'points' => 5, 'files' => ['forensics/ez_challenges!!!!!/meta_yarn.jpg'], 'answer' => 'psuCTF{metadata_exists}'],
    'simple encoding'    => ['title' => 'Simple Encoding',       'desc' => 'File: base64_reverse.txt. The file contains encoded text. Decode it properly and investigate the result carefully.', 'points' => 5, 'files' => ['forensics/ez_challenges!!!!!/base64_reverse.txt'], 'answer' => 'psuCTF{double_layer}'],
    'hidden doc'         => ['title' => 'Hidden in Plain Sight (Doc)', 'desc' => 'File: hidden_in_plain_sight.docx. Not all hidden information is deeply buried. Look beyond what is immediately visible and examine it from different angles.', 'points' => 5, 'files' => ['forensics/ez_challenges!!!!!/hidden_in_plain_sight.docx'], 'answer' => 'psuCTF{zoom_in}'],
    'overthink malala'   => ['title' => 'Overthink Malala',      'desc' => 'File: disregard_FLAG{check_txt_files_properly}.txt. Not every file that appears important contains useful information. Do not assume the obvious path is the correct one.', 'points' => 5, 'files' => ['forensics/ez_challenges!!!!!/disregard_FLAG{check_txt_files_properly}.txt'], 'answer' => 'psuCTF{check_txt_files_properly}'],
    'hex warmup'         => ['title' => 'Hex Warmup',            'desc' => 'File: HEXagon.txt. A suspicious text file was recovered from a workstation. The contents appear to be encoded. What is the decoded message?', 'points' => 10, 'files' => ['forensics/1_hex_warmup/HEXagon.txt'], 'answer' => 'psuCTF{first_win_easy}'],
    'hidden image'       => ['title' => 'Hidden in Plain Sight (Image)', 'desc' => 'File: image.png. An image found on a suspect\'s device looks completely normal, but investigators believe it contains hidden information. What is the hidden message?', 'points' => 30, 'files' => ['forensics/2_stego/image.png'], 'answer' => 'psuCTF{hidden_in_plain_sight}'],
    'embedded evidence'  => ['title' => 'Embedded Evidence',     'desc' => 'File: challenge.jpg. The image opens normally, but analysts suspect additional data is embedded within it. What is the extracted flag?', 'points' => 50, 'files' => ['forensics/3_walkbin/challenge.jpg'], 'answer' => 'psuCTF{file_carving_success}'],
    'suspicious network' => ['title' => 'Suspicious Network Activity', 'desc' => 'File: traffic.pcap. A packet capture was obtained after a suspected unauthorized login attempt on a web server. Clue: username+password = FLAG{username_password}', 'points' => 100, 'files' => ['forensics/4_wiresharkdododo/traffic.pcap'], 'answer' => 'psuCTF{admin_hack4gov}'],
  ],

  // TODO: replace every 'FLAG{TODO_...}' answer with the real flag and adjust the points.
  'osint' => [
    'door 1'       => ['title' => 'Ladder',       'desc' => 'Does the name \'PeeESHyou\' ring a bell? Maybe something on social media can unlock these doors, perhaps a password. Note: Open Door 1, 2 and 3 to obtain the flag. Flag is at Door 3. After seeing it there, push the answer on the website to get your score.', 'points' => 20, 'files' => ['OSINT\Door 1, Door 2, Door 3\challenge.zip'], 'answer' => 'psuCTF{Mikurajima, Japan}'],
    'just nels it' => ['title' => 'Just Nels It!', 'desc' => 'File: so many trees.jpg. So many trees... can you figure out where this photo was taken? \n Use underscore as spacing for answer format ', 'points' => 30, 'files' => ['OSINT/Just Nels It!/so many trees.jpg'], 'answer' => 'psuCTF{Amazon_Rainforest}'],
    'time machine' => ['title' => 'Time Machine',  'desc' => 'A guy named john started building his very own personal website long ago. During development he left JUICY information. Maybe a machine capable of going way back in time could retrieve it? Website: https://ratatooo-pixel.github.io/my-website/', 'points' => 30, 'answer' => 'psuCTF{w4yb4ck_h0m3p4g3_2004}'],
  ],

  
  'steganography' => [
    'problem one'   => ['title' => 'Use the tool',   'desc' => ' A strange image indeed...could be there is something inside it? Use something to pull it out. Oh, and a code came with it: dGhpcy1pcy1ub3QtdGhlLXBhc3N3b3JkLXRob3VnaA==', 'points' => 30, 'files' => ['steganography/steghide.jpg'],   'answer' => 'psuCTF{welc0m3_t0_st3g}'],
    'problem two'   => ['title' => 'listen look and listen and learn',   'desc' => 'A strange audio recording was recovered. What message could it contain...', 'points' => 20, 'files' => ['steganography/signal.wav'],   'answer' => 'psuCTF{th3_sound_!s_us3less}'],
    'problem three' => ['title' => 'The Last GIFbender', 'desc' => 'It moves, it loops, it’s definitely a GIF. But look closer. What’s really going on underneath the motion?', 'points' => 80, 'files' => ['steganography/challenge.gif'], 'answer' => 'psuCTF{g!f_mast3r}'],
  ],
];