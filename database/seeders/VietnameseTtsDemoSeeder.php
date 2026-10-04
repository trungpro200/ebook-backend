<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class VietnameseTtsDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $owner = User::query()->where('email', 'vietnamese-tts-demo@mocthu.invalid')->first();

            if ($owner === null) {
                $owner = new User;
                $owner->name = 'Vietnamese TTS Demo Import';
                $owner->email = 'vietnamese-tts-demo@mocthu.invalid';
                $owner->password = Str::random(64);
                $owner->role = User::ROLE_ADMIN;
                $owner->save();
            } elseif ($owner->role !== User::ROLE_ADMIN) {
                throw new RuntimeException('The Vietnamese TTS demo owner email belongs to a non-admin user.');
            }

            $category = Category::query()->firstOrCreate(
                ['name' => 'Truyện mẫu TTS'],
                ['description' => 'Truyện tiếng Việt viết riêng để thử tính năng AI Voice.'],
            );

            foreach ($this->books() as $definition) {
                $book = Book::query()->firstOrCreate(
                    ['author_id' => $owner->id, 'title' => $definition['title']],
                    [
                        'category_id' => $category->id,
                        'author_name' => 'Mộc Thư (truyện mẫu)',
                        'description' => $definition['description'],
                        'language' => 'vi',
                        'status' => 'completed',
                    ],
                );

                foreach ($definition['chapters'] as $number => $chapter) {
                    $book->chapters()->firstOrCreate(
                        ['chapter_number' => $number + 1],
                        ['title' => $chapter['title'], 'content' => $chapter['content']],
                    );
                }
            }
        });
    }

    /**
     * @return list<array{title: string, description: string, chapters: list<array{title: string, content: string}>}>
     */
    private function books(): array
    {
        return [
            [
                'title' => '[Thử TTS] Chuyến tàu qua đèo',
                'description' => 'Truyện mẫu về một chuyến tàu sớm, chiếc vali bị bỏ quên và cuộc gặp gỡ bất ngờ.',
                'chapters' => [
                    [
                        'title' => 'Chương 1: Sân ga lúc bình minh',
                        'content' => <<<'TEXT'
Trời vừa hửng sáng khi An bước vào sân ga. Mưa đêm còn đọng trên mái tôn, từng giọt nước rơi xuống nền gạch thành những tiếng lách tách đều đặn. Ở cuối sân, người bán hàng đang mở nắp nồi xôi. Hơi nóng bay lên, mang theo mùi lá chuối và đậu xanh giữa không khí se lạnh.

An kéo chiếc vali nhỏ đến toa số ba. Cô định đi thăm bà ngoại sau nhiều tháng chỉ được nghe giọng bà qua điện thoại. Trong túi áo, tấm vé đã mềm đi vì bị cô lấy ra xem quá nhiều lần. Chuyến tàu khởi hành lúc sáu giờ mười lăm, còn đúng mười phút nữa. An thầm nhắc mình phải lên tàu trước khi tiếng còi vang lên.

Một ông cụ đứng gần cửa toa đang cúi xuống tìm kính. Chiếc khăn len màu nâu của ông mắc vào quai túi, khiến mọi thứ trên tay ông rơi xuống. An đặt vali sang một bên rồi giúp ông nhặt từng món: một cuốn sổ bìa xanh, hai quả quýt và một tấm ảnh đã cũ. Trong ảnh, một cô bé cười rất tươi bên cây cầu gỗ.

“Cảm ơn cháu,” ông cụ nói. “Đôi mắt này bây giờ chỉ nhìn rõ khi trời đủ sáng.” An đưa lại cuốn sổ và mỉm cười. Ông bảo mình cũng xuống ở ga cuối, nơi con sông uốn một vòng quanh chân đèo. Khi tàu chuyển bánh, hai người ngồi đối diện nhau bên cửa sổ. Những mái nhà thấp lùi dần về phía sau, rồi nhường chỗ cho ruộng lúa ướt sương.

An nhìn ra ngoài và nghĩ về bà. Lần cuối gặp nhau, bà đã hứa sẽ dạy cô làm bánh tro. Chuyện ấy tưởng nhỏ, nhưng suốt mùa hè vừa qua An vẫn nhớ rõ. Cô muốn kể cho bà nghe về công việc mới, về khu vườn nhỏ trên ban công, và cả những ngày mình thấy mệt mà không biết nói với ai.

Đến khúc cua đầu tiên, An chợt nhận ra chiếc vali của mình không còn ở dưới ghế. Cô đứng bật dậy, nhìn dọc lối đi. Một chiếc vali giống hệt nằm cạnh cửa toa, nhưng trên tay kéo có buộc dải ruy băng đỏ. Vali của An không có dải ruy băng nào. Tàu đang tăng tốc, còn sân ga đã khuất sau hàng cây.
TEXT,
                    ],
                    [
                        'title' => 'Chương 2: Chiếc vali màu xanh',
                        'content' => <<<'TEXT'
An đi dọc toa tàu, cố nhớ mình đã đặt vali ở đâu. Cô hỏi người soát vé, hỏi chị bán nước và cả hai hành khách ngồi gần cửa. Ai cũng lắc đầu. Ông cụ cầm cuốn sổ bìa xanh đi theo, chậm rãi quan sát những hàng ghế. Cuối cùng ông chỉ vào chiếc vali có dải ruy băng đỏ và hỏi: “Cháu đã mở thử chưa?”

An lắc đầu. Cô không muốn đụng vào đồ của người khác. Người soát vé kiểm tra thẻ tên gắn ở quai xách. Trên đó viết: “Minh, ga Suối Đá.” Ga Suối Đá nằm trước ga của bà ngoại hai trạm. Người soát vé gọi qua bộ đàm, nhờ đồng nghiệp kiểm tra xem có ai đang tìm một chiếc vali màu xanh không.

Chưa đầy năm phút sau, một cậu bé từ toa bên cạnh chạy tới. Cậu ôm chặt chiếc mũ vải trong tay và thở hổn hển. “Em lấy nhầm,” cậu nói. “Hai chiếc giống nhau quá.” Cậu đưa An chiếc vali không có ruy băng. An bật cười vì nhẹ nhõm. Cô nhận ra mình cũng đã vội đến mức không nhìn kỹ.

Ông cụ kể rằng ngày còn trẻ ông từng xuống nhầm ga, phải đi bộ gần mười cây số để quay lại. “Mỗi chuyến đi đều có một chuyện để nhớ,” ông nói. An ngồi xuống, kéo khóa vali kiểm tra tấm khăn cho bà và hộp bánh nhỏ mình mang theo. Mọi thứ vẫn nguyên vẹn. Ngoài cửa sổ, mây trôi chậm qua những ngọn đồi xanh.

Khi tàu tới ga cuối, bà ngoại đã đứng chờ dưới mái hiên. Bà mặc chiếc áo khoác cũ, một tay giữ chiếc ô, tay kia vẫy thật cao. An bước xuống, gọi bà giữa tiếng người qua lại. Cơn mưa đã tạnh. Ông cụ đi ngang qua, gật đầu chào rồi tiếp tục hành trình của mình. An nắm tay bà, nghe bà hỏi có mệt không, có đói không, và có còn nhớ lời hứa làm bánh tro năm ấy không.
TEXT,
                    ],
                ],
            ],
            [
                'title' => '[Thử TTS] Tiệm sách dưới mưa',
                'description' => 'Truyện mẫu về một tiệm sách cũ, những tờ giấy ghi chú và người hàng xóm mới.',
                'chapters' => [
                    [
                        'title' => 'Chương 1: Mùi giấy cũ',
                        'content' => <<<'TEXT'
Buổi chiều, mưa kéo đến bất ngờ. Lan nép dưới mái hiên của một tiệm sách cũ trên con phố cô thường đi ngang qua. Tấm biển gỗ treo trước cửa ghi hai chữ “Góc Nhỏ”. Bên trong, ánh đèn vàng hắt lên những kệ sách cao tới trần. Lan định đứng đợi mưa ngớt, nhưng tiếng chuông gió ở cửa khẽ ngân khi cô vừa đưa tay chạm vào tay nắm.

Người trông tiệm là một phụ nữ tóc bạc, đang ngồi sửa gáy một cuốn sách bằng chiếc cọ nhỏ. Bà mời Lan vào và đưa cô một chiếc khăn khô. “Trời mưa thì sách cần được giữ ấm,” bà nói đùa. Lan cười, đi giữa những lối hẹp và nhìn các nhãn giấy viết tay: truyện ngắn, địa lý, khoa học, nấu ăn. Mùi giấy cũ khiến cô nhớ đến căn phòng của cha ngày trước.

Ở kệ gần cửa sổ, Lan tìm thấy một cuốn sách về các khu vườn. Giữa trang ba mươi hai có một tờ giấy nhỏ: “Nếu hôm nay khó khăn, hãy bắt đầu bằng việc tưới một chậu cây.” Không có tên người viết. Lan đọc lại câu ấy hai lần. Từ khi chuyển đến thành phố, cô đã bận tới mức quên cả chậu bạc hà đặt ngoài ban công.

Một người thanh niên bước vào tiệm, gấp chiếc ô màu xanh rồi cúi chào bà chủ. Anh mang đến một hộp bánh còn ấm và hỏi chiếc đồng hồ treo tường đã chạy đúng giờ chưa. Bà chủ chỉ lên mặt đồng hồ đang chậm bảy phút. Anh lấy ghế đứng lên chỉnh lại kim, cẩn thận như thể đó là việc quan trọng nhất trong ngày.

Lan đặt cuốn sách lên quầy. Bà chủ nhìn tờ giấy ghi chú và nói: “Tiệm này có một thói quen nhỏ. Ai tìm được lời nhắn thì có thể để lại một lời nhắn khác.” Lan cầm cây bút chì, suy nghĩ một lúc lâu. Ngoài phố, mưa vẫn rơi đều. Cô viết vào một mảnh giấy mới: “Ngày mai có thể bắt đầu bằng một việc rất nhỏ.” Rồi cô kẹp nó vào một trang khác, nơi người đọc sau sẽ tự tìm thấy.
TEXT,
                    ],
                    [
                        'title' => 'Chương 2: Lời nhắn ở trang cuối',
                        'content' => <<<'TEXT'
Sáng hôm sau, Lan tưới chậu bạc hà trước khi ra khỏi nhà. Những chiếc lá nhỏ đã hơi rũ xuống, nhưng chỉ một lúc sau chúng đứng thẳng hơn. Cô nghĩ đến lời nhắn trong cuốn sách và quyết định quay lại tiệm sau giờ làm. Lần này trời trong, nắng chiều nằm thành một vệt dài trên vỉa hè.

Bà chủ tiệm đang sắp xếp một thùng sách mới. Người thanh niên hôm qua cũng có mặt. Anh tên là Huy và làm việc ở cửa hàng sửa đồng hồ cách đó hai con phố. Khi nghe Lan hỏi về những tờ giấy ghi chú, Huy kể rằng trước kia cha anh thường để lại lời nhắn cho khách trong các cuốn sách. Bà chủ tiếp tục thói quen ấy sau khi ông mất, vì nhiều người đã quay lại để kể rằng một câu ngắn đã giúp họ vượt qua một ngày dài.

Lan giúp bà xếp sách lên kệ. Cô phát hiện những cuốn cũ thường có vết gấp ở trang người ta yêu thích nhất. Có cuốn lưu một chiếc vé xe buýt, có cuốn ép một bông hoa đã khô. Mỗi dấu vết khiến câu chuyện trong sách dường như có thêm một người kể. Lan chọn một cuốn truyện ngắn, còn Huy tìm được tập bản đồ thành phố từ nhiều năm trước.

Khi tiệm sắp đóng cửa, bà chủ đưa Lan một chiếc phong bì nhỏ. Bên trong là lời nhắn mà ai đó vừa tìm thấy trong cuốn sách về khu vườn. Người ấy đã viết tiếp: “Tôi đã tưới cây. Cảm ơn người để lại câu này.” Lan không biết đó là ai, và cũng không cần biết. Cô chỉ cảm thấy căn phòng bỗng sáng hơn một chút.

Trên đường về, Lan đi chậm qua cửa hàng sửa đồng hồ. Chiếc đồng hồ lớn trước cửa đã chỉ đúng giờ. Cô nhớ mình còn một việc muốn làm: mua thêm một chậu cây cho ban công. Bạc hà sẽ có bạn, còn cô sẽ có một lý do nhỏ để thức dậy sớm hơn vào ngày mai.
TEXT,
                    ],
                ],
            ],
            [
                'title' => '[Thử TTS] Con đường lên ngọn hải đăng',
                'description' => 'Truyện mẫu về một buổi sáng ven biển và những tín hiệu từ ngọn hải đăng.',
                'chapters' => [
                    [
                        'title' => 'Chương 1: Ngọn đèn cuối mũi đất',
                        'content' => <<<'TEXT'
Biển buổi sáng có màu xanh nhạt. Từ căn nhà nhỏ trên sườn đồi, Bình nhìn thấy ngọn hải đăng trắng đứng ở cuối mũi đất. Cha cậu vẫn thường nói rằng ánh đèn ấy là lời chào dành cho những con thuyền trở về. Hôm nay, ngọn đèn đã tắt khi mặt trời lên, nhưng Bình vẫn muốn tới đó. Người gác đèn hẹn sẽ chỉ cho cậu cách đọc những tín hiệu ngoài khơi.

Đường lên hải đăng chạy qua một rừng phi lao. Gió thổi làm cành lá va vào nhau như tiếng mưa. Bình mang theo bình nước, chiếc bánh mì mẹ gói và quyển sổ để ghi chép. Cậu đi chậm, đếm từng bậc đá. Sau cơn bão tuần trước, vài bậc bị phủ cát, còn hai đoạn lan can cần được sửa lại.

Người gác đèn tên là bác Tư. Bác đang lau cửa kính lớn khi Bình đến. Từ trên cao, những chiếc thuyền đánh cá trông bé như những nét mực trên mặt nước. Bác Tư chỉ một lá cờ đỏ trên thuyền gần bờ và giải thích đó là dấu hiệu tàu đang xin trợ giúp. Bình nhìn thật kỹ, rồi phát hiện một thuyền khác đang chuyển hướng về phía chiếc tàu ấy.

“Có phải lúc nào bác cũng phải nhìn ra biển không?” Bình hỏi. Bác Tư lắc đầu. “Quan trọng là biết nhìn đúng lúc, và biết hỏi khi mình chưa chắc.” Bác đưa Bình chiếc ống nhòm cũ. Qua hai thấu kính, cậu thấy rõ những con sóng nhỏ đập vào mạn thuyền. Chiếc thuyền xin trợ giúp đã hạ lá cờ. Một người trên boong giơ tay vẫy về phía bờ.

Bình ghi vào sổ ngày, giờ và hướng gió. Cậu muốn kể cho cha nghe rằng biển không chỉ có màu xanh và tiếng sóng. Trên biển còn có những tín hiệu nhỏ, những người dõi theo nhau và những con đường không được vẽ trên mặt đất.
TEXT,
                    ],
                    [
                        'title' => 'Chương 2: Tín hiệu lúc hoàng hôn',
                        'content' => <<<'TEXT'
Buổi chiều, mây xám tụ lại phía chân trời. Bình định xuống đồi trước khi trời tối, nhưng bác Tư nhờ cậu giúp ghi lại hướng của ba chiếc thuyền vừa rời bến. Hai người đứng trong phòng kính, nhìn ánh nắng nhạt dần trên mặt biển. Bác Tư bật thử ngọn đèn. Một vệt sáng quét ngang cửa sổ, rồi biến mất sau màn mưa xa.

Chiếc thuyền gần nhất bỗng chạy chậm. Bình nhìn qua ống nhòm và thấy một người đang buộc lại tấm bạt trên boong. Cậu báo cho bác Tư biết. Bác gọi điện cho trạm cứu hộ để họ theo dõi, rồi ghi vào sổ trực. Chưa có nguy hiểm, nhưng thời tiết có thể đổi nhanh. Bình hiểu vì sao bác nói phải nhìn đúng lúc.

Mưa tới khi ngọn đèn đã sáng đều. Những giọt nước đập lên kính, gió rít qua khe cửa. Bình lo cho những người đang ở ngoài khơi. Bác Tư đặt trước mặt cậu một cốc trà ấm và bảo: “Mình cứ làm thật tốt phần việc của mình. Ánh đèn này giúp họ biết bờ ở đâu.” Bình ngồi cạnh cửa sổ, im lặng theo dõi từng vòng sáng.

Một giờ sau, ba chiếc thuyền lần lượt hiện ra gần cửa vịnh. Trạm cứu hộ báo rằng mọi người đều an toàn. Bình thở phào, cầm bút ghi dòng cuối cùng vào sổ. Cậu nhận ra lòng can đảm đôi khi không ồn ào. Nó có thể là một cuộc gọi đúng lúc, một trang sổ rõ ràng, hoặc một ngọn đèn được giữ sáng suốt đêm.

Khi mưa nhẹ đi, cha Bình lên đón cậu. Hai cha con đi xuống con đường đá còn ướt. Phía sau họ, hải đăng tiếp tục xoay chậm trong bóng tối. Bình nắm chặt quyển sổ trong tay. Ngày mai cậu sẽ trở lại để hỏi bác Tư thêm về những lá cờ, những ngôi sao và cách tìm đường khi không thấy bờ.
TEXT,
                    ],
                ],
            ],
        ];
    }
}
