<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;

/**
 * Real editorial content — genuine, publishable articles about branding
 * written for this site, not lorem ipsum and not invented case studies.
 *
 * Every claim here is general professional practice rather than a
 * statistic, a client result or anything else that would need a source.
 *
 * Slugs are explicit so re-running matches rather than duplicating.
 */
class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->articles() as $offset => $article) {
            Article::updateOrCreate(
                ['slug' => $article['slug']],
                [
                    'title' => $article['title'],
                    'excerpt' => $article['excerpt'],
                    'body' => $article['body'],
                    'is_published' => true,
                    // Staggered into the past so the blog index has a real
                    // ordering to show, and so nothing is dated in the
                    // future on a fresh install.
                    'published_at' => now()->subDays(($offset + 1) * 7),
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function articles(): array
    {
        return [
            [
                'slug' => 'brand-is-not-a-logo',
                'title' => [
                    'ar' => 'العلامة التجارية ليست شعاراً',
                    'en' => 'A Brand Is Not a Logo',
                ],
                'excerpt' => [
                    'ar' => 'الشعار علامة تعريف، أما العلامة التجارية فهي ما يتذكره الناس عنك بعد أن يغادروا.',
                    'en' => 'A logo is an identifier. A brand is what people remember about you after they leave.',
                ],
                'body' => [
                    'ar' => '<p>يبدأ كثير من العملاء الحديث بجملة "نريد شعاراً". وهو طلب مفهوم، لكنه يصف عادةً حاجة أكبر بكثير.</p>'
                        .'<h2>ما الفرق؟</h2>'
                        .'<p>الشعار رمز بصري يميّزك عن غيرك. العلامة التجارية هي الانطباع الكامل الذي يتكوّن لدى الناس عنك: كيف تتحدث، وكيف تبدو، وكيف تتعامل مع عميل غاضب، وما الذي تَعِد به وتفي به فعلاً.</p>'
                        .'<p>الشعار جزء من ذلك، لكنه أصغر جزء. لهذا فإن شركة بشعار متقن وخدمة سيئة تبقى علامة ضعيفة، بينما شركة بشعار بسيط وتجربة ممتازة تبني علامة قوية.</p>'
                        .'<h2>لماذا يهم هذا عملياً؟</h2>'
                        .'<p>لأن ترتيب العمل يتغيّر. إذا كان المطلوب شعاراً فقط، يبدأ المصمم بالرسم. أما إذا كان المطلوب علامة، فالبداية أسئلة: من جمهورك؟ ما الذي يميّزك عن المنافس المجاور؟ ما نبرة صوتك؟ الإجابات هي ما يوجّه الرسم لاحقاً، ويجعل الشعار الناتج قراراً مبرَّراً لا خياراً جمالياً.</p>'
                        .'<h2>من أين تبدأ؟</h2>'
                        .'<p>ابدأ بكتابة ما تريد أن يقوله عنك عميلك لصديقه. جملة واحدة. إن استطعت كتابتها بوضوح، فأنت جاهز لمرحلة التصميم. وإن لم تستطع، فهذه هي المرحلة التي تحتاجها أولاً.</p>',
                    'en' => '<p>A lot of conversations start with "we need a logo". It is an understandable request, and it usually describes a much bigger need.</p>'
                        .'<h2>What is the difference?</h2>'
                        .'<p>A logo is a visual mark that distinguishes you. A brand is the whole impression people form of you: how you speak, how you look, how you handle an unhappy customer, and what you promise versus what you actually deliver.</p>'
                        .'<p>The logo is part of that, but it is the smallest part. Which is why a company with an immaculate logo and poor service still has a weak brand, while a company with a plain mark and an excellent experience builds a strong one.</p>'
                        .'<h2>Why does this matter in practice?</h2>'
                        .'<p>Because it changes the order of the work. If the job is a logo, the designer starts drawing. If the job is a brand, it starts with questions: who is your audience? What distinguishes you from the competitor next door? What is your tone of voice? Those answers are what direct the drawing later, and what make the resulting mark a justified decision rather than an aesthetic preference.</p>'
                        .'<h2>Where to start</h2>'
                        .'<p>Write down what you want a customer to say about you to a friend. One sentence. If you can write it clearly, you are ready to design. If you cannot, that is the stage you need first.</p>',
                ],
            ],
            [
                'slug' => 'arabic-typography-in-branding',
                'title' => [
                    'ar' => 'الخط العربي في الهوية البصرية: أخطاء شائعة',
                    'en' => 'Arabic Typography in Branding: Common Mistakes',
                ],
                'excerpt' => [
                    'ar' => 'أكثر الهويات ثنائية اللغة تفشل في النصف العربي منها، ولأسباب يمكن تفاديها.',
                    'en' => 'Most bilingual identities fail on the Arabic half — for reasons that are entirely avoidable.',
                ],
                'body' => [
                    'ar' => '<p>عندما تُصمَّم الهوية بالإنجليزية أولاً ثم "تُترجم"، تظهر مشاكل متكررة في النسخة العربية. هذه أكثرها شيوعاً.</p>'
                        .'<h2>١. اختيار الخط بناءً على شكله لا على قراءته</h2>'
                        .'<p>كثير من الخطوط العربية مصمَّمة للعناوين الكبيرة فقط. استخدامها في نص طويل يجعل القراءة متعبة. اختر خطاً له عائلة كاملة بأوزان متعددة، وجرّبه في فقرة حقيقية قبل اعتماده.</p>'
                        .'<h2>٢. إجبار العربي على مطابقة اللاتيني</h2>'
                        .'<p>الحرف العربي متصل وله ارتفاعات مختلفة عن الحرف اللاتيني. محاولة جعلهما متطابقين بصرياً تنتهي عادةً بتشويه أحدهما. الهدف هو التناغم، لا التطابق.</p>'
                        .'<h2>٣. تجاهل اتجاه القراءة في التصميم</h2>'
                        .'<p>عكس اتجاه الصفحة لا يكفي. مسار عين القارئ العربي يبدأ من اليمين، وهذا يعني أن ترتيب العناصر وأولوياتها يجب أن يُعاد التفكير فيه، لا أن يُقلَب فقط.</p>'
                        .'<h2>٤. تصميم شعار عربي لاحقاً</h2>'
                        .'<p>إذا كانت العلامة ستعمل بلغتين، صمّم النسختين في نفس الوقت. الشعار العربي المضاف بعد اعتماد اللاتيني يبدو دائماً كملحق، لأنه كذلك فعلاً.</p>',
                    'en' => '<p>When an identity is designed in English first and then "translated", the same problems appear in the Arabic version. These are the most common.</p>'
                        .'<h2>1. Choosing a typeface for its shape rather than its legibility</h2>'
                        .'<p>Many Arabic typefaces are designed for large display use only. Setting body copy in them is tiring to read. Choose a face with a full family of weights, and test it in a real paragraph before committing.</p>'
                        .'<h2>2. Forcing Arabic to match Latin</h2>'
                        .'<p>Arabic script is connected and has different vertical proportions from Latin. Trying to make the two visually identical usually ends up distorting one of them. The goal is harmony, not equivalence.</p>'
                        .'<h2>3. Ignoring reading direction in the layout</h2>'
                        .'<p>Flipping the page is not enough. An Arabic reader\'s eye starts from the right, which means the order and priority of elements has to be rethought, not merely mirrored.</p>'
                        .'<h2>4. Designing the Arabic logo afterwards</h2>'
                        .'<p>If the brand will operate in two languages, design both versions at the same time. An Arabic mark added after the Latin one is approved always looks like an attachment — because that is what it is.</p>',
                ],
            ],
            [
                'slug' => 'what-to-prepare-before-a-branding-project',
                'title' => [
                    'ar' => 'ما الذي تجهّزه قبل بدء مشروع هوية؟',
                    'en' => 'What to Prepare Before a Branding Project',
                ],
                'excerpt' => [
                    'ar' => 'التحضير الجيد يختصر المشروع ويقلّل المراجعات. هذه قائمة عملية بما تحتاجه.',
                    'en' => 'Good preparation shortens a project and cuts revision rounds. Here is a practical checklist.',
                ],
                'body' => [
                    'ar' => '<p>أغلب التأخير في مشاريع الهوية لا يأتي من التصميم، بل من انتظار معلومات كان يمكن تجهيزها مسبقاً. هذه قائمة عملية.</p>'
                        .'<h2>الاسم النهائي</h2>'
                        .'<p>تأكد من اعتماد الاسم قانونياً وتوفّره كنطاق وكحسابات على المنصات، قبل أن يبدأ التصميم. تغيير الاسم في منتصف المشروع يعني إعادة العمل من البداية.</p>'
                        .'<h2>وصف مكتوب لنشاطك</h2>'
                        .'<p>فقرة واحدة تشرح ما تقدّمه ولمن. إن اختلف شريكان في هذه الفقرة، فالخلاف سيظهر لاحقاً في مراجعات التصميم — الأفضل حسمه الآن.</p>'
                        .'<h2>ثلاثة منافسين</h2>'
                        .'<p>أسماء ومواقع ثلاث علامات تنافسك أو تعمل في مجالك. هذا يوفّر على الفريق أسبوعاً من البحث، ويضمن أن نتيجة التصميم لن تشبه المنافس المباشر.</p>'
                        .'<h2>أمثلة تعجبك وأخرى لا تعجبك</h2>'
                        .'<p>الأمثلة السلبية أهم من الإيجابية عادةً، لأنها تحدد الحدود بسرعة. لا يشترط أن تكون من مجالك.</p>'
                        .'<h2>قائمة المخرجات</h2>'
                        .'<p>أين ستستخدم الهوية فعلاً؟ بطاقة أعمال؟ واجهة محل؟ تغليف؟ تطبيق؟ كل مخرج له متطلبات تقنية مختلفة، ومعرفتها مسبقاً تمنع مفاجآت التنفيذ.</p>'
                        .'<h2>جهة اتخاذ القرار</h2>'
                        .'<p>حدّد من يعتمد التصميم نهائياً. المشاريع التي يراجعها خمسة أشخاص بلا مرجع واحد هي أطول المشاريع وأكثرها مراجعات.</p>',
                    'en' => '<p>Most delay in identity projects does not come from designing — it comes from waiting on information that could have been prepared in advance. Here is a practical checklist.</p>'
                        .'<h2>The final name</h2>'
                        .'<p>Make sure the name is legally registered and available as a domain and as handles, before design begins. Changing the name mid-project means starting the work again.</p>'
                        .'<h2>A written description of your business</h2>'
                        .'<p>One paragraph explaining what you offer and to whom. If two founders disagree on that paragraph, the disagreement will resurface during design reviews — better to settle it now.</p>'
                        .'<h2>Three competitors</h2>'
                        .'<p>Names and links for three brands you compete with or that operate in your space. This saves the team a week of research and makes sure the result does not resemble your nearest competitor.</p>'
                        .'<h2>Examples you like and examples you do not</h2>'
                        .'<p>The negative examples are usually more useful than the positive ones, because they set boundaries quickly. They do not have to come from your industry.</p>'
                        .'<h2>A list of deliverables</h2>'
                        .'<p>Where will the identity actually be used? Business cards? A shopfront? Packaging? An app? Each has different technical requirements, and knowing them up front prevents production surprises.</p>'
                        .'<h2>Who decides</h2>'
                        .'<p>Name the person who gives final approval. Projects reviewed by five people with no single decision-maker are the longest projects with the most revision rounds.</p>',
                ],
            ],
        ];
    }
}
