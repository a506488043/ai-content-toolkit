jQuery(document).ready(function($) {
    // Function to calculate age (simplified for client-side)
    function calculateAgeClient(birthdateStr) {
        const birth = new Date(birthdateStr);
        const today = new Date();

        if (birth > today) {
            return { error: "出生日期不能晚于今天" };
        }

        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            age--;
        }

        const timeDiff = today.getTime() - birth.getTime();
        const totalDays = Math.floor(timeDiff / (1000 * 3600 * 24));

        const nextBirthday = new Date(today.getFullYear(), birth.getMonth(), birth.getDate());
        if (nextBirthday < today) {
            nextBirthday.setFullYear(today.getFullYear() + 1);
        }
        
        const daysToNextBirthday = Math.ceil((nextBirthday.getTime() - today.getTime()) / (1000 * 3600 * 24));

        return {
            age: age,
            totalDays: totalDays,
            daysToNextBirthday: daysToNextBirthday,
            isToday: daysToNextBirthday === 0
        };
    }

    // Handle form submission for interactive calculator
    $(document).on("submit", ".manus-age-calculator-form", function(e) {
        e.preventDefault();
        const form = $(this);
        const birthdateInput = form.find(".manus-age-calculator-birthdate");
        const resultDiv = form.siblings(".manus-age-calculator-result");
        
        resultDiv.html(""); // Clear previous results

        const birthdate = birthdateInput.val();

        if (!birthdate) {
            resultDiv.html("<p style=\"color: red;\">请输入出生日期。</p>");
            return;
        }

        const calculationResult = calculateAgeClient(birthdate);

        if (calculationResult.error) {
            resultDiv.html("<p style=\"color: red;\">" + calculationResult.error + "</p>");
        } else {
            let output = 
                `<div class="manus-age-calculator-result success">
                    <div>您的年龄是: <strong>${calculationResult.age}岁</strong></div>
                    <div class="manus-age-calculator-info">您已经生活了 ${calculationResult.totalDays} 天</div>`;
            
            if (calculationResult.isToday) {
                output += `<div class="manus-age-calculator-info">🎉 今天是您的生日！</div>`;
            } else {
                output += `<div class="manus-age-calculator-info">距离下次生日: ${calculationResult.daysToNextBirthday} 天</div>`;
            }
            output += `</div>`;
            resultDiv.html(output);
        }
    });

    // Populate birthdate for logged-in users if available
    if (typeof manusAgeCalculatorData !== 'undefined' && manusAgeCalculatorData.isLoggedIn && manusAgeCalculatorData.userBirthdate) {
        $(".manus-age-calculator-birthdate").val(manusAgeCalculatorData.userBirthdate);
    }
});

