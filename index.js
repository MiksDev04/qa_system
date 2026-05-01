const BASE_URL = 'https://artisanslms.infinityfree.me/artisansLMS/backend/api/export_student_performance.php';
const API_KEY = '0fvBAvRhGAkES6QVHXYojIVDQq5iPiRl';

async function fetchPerformance(action, options = {}) {
  let url = `${BASE_URL}?api_key=${API_KEY}&action=${action}`;

  if (options.student_id) url += `&student_id=${options.student_id}`;
  if (options.year)       url += `&year=${options.year}`;
  if (options.semester)   url += `&semester=${encodeURIComponent(options.semester)}`;

  const response = await fetch(url, {
    method: 'GET'
  });

  if (!response.ok) {
    throw new Error(`Request failed: ${response.status} ${response.statusText}`);
  }

  return await response.json();
}


// --- Usage examples ---

// Get overview
fetchPerformance('get_overview')
  .then(data => console.log(data))
  .catch(err => console.error(err));

// Get a specific student
fetchPerformance('get_students', { student_id: 4 })
  .then(data => console.log(data))
  .catch(err => console.error(err));

// Get overview filtered by year and semester
fetchPerformance('get_overview', { year: '2024', semester: 'First' })
  .then(data => console.log(data))
  .catch(err => console.error(err));

// Get courses
fetchPerformance('get_courses')
  .then(data => console.log(data))
  .catch(err => console.error(err));

// Get instructors
fetchPerformance('get_instructors')
  .then(data => console.log(data))
  .catch(err => console.error(err));