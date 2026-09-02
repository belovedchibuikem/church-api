<?php

namespace App\Support\Kca;

final class KcaAdmissionLetterDefaultTemplate
{
    public const PLACEHOLDER_HELP = <<<'TEXT'
Available placeholders:
{reference_code}, {date}, {applicant_name}, {applicant_first_name}, {applicant_address}, {applicant_phone},
{church_name}, {kca_year}, {programme_commencement}, {programme_completion}, {venue},
{training_schedule}, {assigned_mentor}, {signer_name}, {signer_title},
{applicant_signature}, {applicant_acceptance_date}, {guardian_name}, {guardian_signature},
{guardian_phone}, {guardian_acceptance_date}
TEXT;

    public static function body(): string
    {
        return <<<'TEXT'
THE FAMILY HOUSE OF GOD INTERNATIONAL
KINGDOM CHANGE AGENTS (KCA)
YOUTH DISCIPLESHIP TRAINING PROGRAMME
ADMISSION & ACCEPTANCE LETTER

Ref. No.: {reference_code}
Date: {date}

To:
Name of Applicant: {applicant_name}
Address: {applicant_address}

Dear {applicant_first_name},

RE: ADMISSION INTO THE KINGDOM CHANGE AGENTS (KCA) DISCIPLESHIP TRAINING PROGRAMME

We are pleased to inform you that, following the review of your enrolment application, you have been accepted into the Kingdom Change Agents (KCA) Youth Discipleship Training Programme of The Family House of God International.

We congratulate you on this opportunity and warmly welcome you into this discipleship journey.

KCA has been established to help young people develop a genuine relationship with Jesus Christ, grow in the knowledge of God's Word, develop godly character, discover their place in the Church, serve faithfully and become positive Kingdom influences in their generation.

Our desire is not merely to train you to complete a programme, but to help you become a committed disciple of Jesus Christ who is prepared to live, equipped to serve, commissioned to influence and sent to multiply.

YOUR KCA COMMITMENT

As an admitted participant, you are expected to take the programme seriously and commit yourself to:

1. FAITHFUL ATTENDANCE
You are required to attend at least 10 of the 12 scheduled sessions.
Regular attendance is an important part of the discipleship process because growth takes place through consistent participation.

2. ACTIVE PARTICIPATION
You are expected to participate actively in:
• Bible studies
• Discussions
• Prayer
• Assignments
• Practical activities
• Mentoring
• Ministry opportunities

3. WRITTEN ASSESSMENTS
You will complete the four required written assignments designed to help you reflect on and demonstrate your understanding of the lessons.

4. PRACTICAL SERVICE
Before graduation, every KCA participant is expected to serve in at least two church departments.
This requirement is intended to help you move from learning about service to actually serving.

5. MENTORSHIP & ACCOUNTABILITY
You will be assigned or connected to an appropriate mentor/leader who will encourage your spiritual development and provide appropriate guidance throughout the programme.

6. CHRISTIAN CONDUCT
You are expected to conduct yourself in a manner consistent with your identity as a follower of Jesus Christ, showing respect, humility, integrity, discipline and love toward others.

YOUR DISCIPLESHIP JOURNEY

During the programme, you will be guided through a twelve-session journey covering:
The Call of the King
Born into the Kingdom
Living as a Child of the King
Walking with the Holy Spirit
At the King's Feet
Becoming Like Jesus
Every Disciple Is a Servant
The Church: God's Family on Mission
Holiness in a Compromised World
Sharing the Gospel
Kingdom Influence
Becoming a Kingdom Change Agent

The ultimate objective is transformation:
Know Christ.
Grow in Christ.
Serve Christ.
Represent Christ.
Influence the world.
Make disciples.

YOUR KCA DECLARATION

As you begin this journey, we encourage you to embrace this declaration:
I will follow Christ.
I will grow in Christ.
I will serve Christ.
I will represent Christ.
I will influence my generation.
I will help others follow Christ.
I AM A KINGDOM CHANGE AGENT.

PROGRAMME DETAILS

KCA Year: {kca_year}
Programme Commencement Date: {programme_commencement}
Programme Completion Date: {programme_completion}
Venue: {venue}
Training Day/Time: {training_schedule}
Assigned Mentor: {assigned_mentor}

FINAL CHARGE

Remember that your admission into KCA is not the destination.
It is the beginning of a deeper journey with Christ.
Do not measure the success of this programme merely by whether you receive a certificate. Measure it by whether you have become more like Jesus, more committed to His Church, more willing to serve and more prepared to influence your generation for the Kingdom of God.

As Paul instructed Timothy:
"Let no man despise thy youth; but be thou an example of the believers..."
— 1 Timothy 4:12 (KJV)

We believe that God can use your life.
We therefore encourage you to approach this journey with humility, discipline, hunger for God's Word and a willingness to be transformed.

Welcome to Kingdom Change Agents.
Living for Christ, Influencing the World.

Yours faithfully,

{signer_name}

{signer_title}
The Family House of God International

APPLICANT'S ACCEPTANCE

I, {applicant_name}, acknowledge my admission into the Kingdom Change Agents (KCA) Youth Discipleship Training Programme.
I have read and understood the expectations outlined in this letter and commit myself to participate faithfully in the programme.

Applicant's Signature: {applicant_signature}
Date: {applicant_acceptance_date}

Parent/Guardian Confirmation
(Required where applicable)

I acknowledge and support the participation of the above-named applicant in the KCA programme.

Parent/Guardian Name: {guardian_name}
Signature: {guardian_signature}
Phone Number: {guardian_phone}
Date: {guardian_acceptance_date}

Prepared to Live • Equipped to Serve • Commissioned to Influence
TEXT;
    }
}
