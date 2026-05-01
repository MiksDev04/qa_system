ArtisansLMS Students Performance API Documentation



Base URL: https://artisanslms.onrender.com/backend/api/export_student_performance.php

Method: GET

Authentication: Pass the API key in the HTTP Header as X-API-Key

ito yung key: **0fvBAvRhGAkES6QVHXYojIVDQq5iPiRl**



Global Time Filters (Optional):

You can append \&year=YYYY and/or \&semester=SemesterName to ANY endpoint to filter the data.

Example 1 (2024 Average): ?action=get\_overview\&year=2024

Example 2 (2026 Average): ?action=get\_overview\&year=2026



Required Parameter: action

Accepted Action Values:



get\_overview (Returns system-wide stats based on filters)



get\_students (Returns all students. Optional param: \&student\_id=X for a specific student)



get\_courses (Returns performance by course)



get\_instructors (Returns performance by instructor)



Deep-Dive Feature:

If you request a specific student (e.g., ?action=get\_students\&student\_id=4), the response will automatically include submission\_history and quiz\_history arrays containing the exact dates, scores, and titles needed for detailed charting.



Response Format: JSON



Example Success Output:

\[{"student\_id":4,"first\_name":"Adi","last\_name":"Bermas","full\_name":"Adi Bermas","email":"dekbermas@gmail.com","enrolled\_classes":7,"total\_submitted":8,"total\_assigned":11,"avg\_grade":100,"quiz\_attempts":1,"quiz\_passed":1,"avg\_quiz\_score":100,"submission\_rate":72.7300000000000039790393202565610408782958984375,"quiz\_pass\_rate":100,"performance\_label":"Excellent"},






