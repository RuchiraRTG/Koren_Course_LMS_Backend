# React Integration Examples

## Fetching Exam Results from Frontend

### 1. Get All Exam Results

```javascript
// components/ExamHistory.jsx
import { useState, useEffect } from 'react';

function ExamHistory() {
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchExamResults();
    }, []);

    const fetchExamResults = async () => {
        try {
            const response = await fetch('http://localhost/getExamResults.php?action=getResults', {
                credentials: 'include' // Important: Include cookies for session
            });
            
            const data = await response.json();
            
            if (data.success) {
                setResults(data.data.results);
            } else {
                console.error('Failed to fetch results:', data.message);
            }
        } catch (error) {
            console.error('Error fetching exam results:', error);
        } finally {
            setLoading(false);
        }
    };

    if (loading) return <div>Loading...</div>;

    return (
        <div className="exam-history">
            <h2>My Exam History</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Exam Type</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Time Taken</th>
                    </tr>
                </thead>
                <tbody>
                    {results.map(result => (
                        <tr key={result.id}>
                            <td>{new Date(result.submittedAt).toLocaleDateString()}</td>
                            <td>{result.examId ? `Exam #${result.examId}` : 'Mock Exam'}</td>
                            <td>{result.score} / {result.totalMarks}</td>
                            <td className={getScoreClass(result.percentage)}>
                                {result.percentage.toFixed(1)}%
                            </td>
                            <td>{Math.round(result.timeTaken / 60)} min</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function getScoreClass(percentage) {
    if (percentage >= 75) return 'score-excellent';
    if (percentage >= 50) return 'score-good';
    return 'score-needs-improvement';
}

export default ExamHistory;
```

### 2. Get Student Statistics

```javascript
// components/StudentDashboard.jsx
import { useState, useEffect } from 'react';

function StudentDashboard() {
    const [stats, setStats] = useState(null);

    useEffect(() => {
        fetchStatistics();
    }, []);

    const fetchStatistics = async () => {
        try {
            const response = await fetch('http://localhost/getExamResults.php?action=getStatistics', {
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                setStats(data.data);
            }
        } catch (error) {
            console.error('Error fetching statistics:', error);
        }
    };

    if (!stats) return <div>Loading...</div>;

    return (
        <div className="dashboard">
            <h1>My Performance Dashboard</h1>
            
            <div className="stats-grid">
                <div className="stat-card">
                    <h3>Total Exams</h3>
                    <div className="value">{stats.totalExams}</div>
                </div>
                
                <div className="stat-card">
                    <h3>Average Score</h3>
                    <div className="value">{stats.averagePercentage}%</div>
                </div>
                
                <div className="stat-card">
                    <h3>Best Score</h3>
                    <div className="value">{stats.bestScore}%</div>
                </div>
                
                <div className="stat-card">
                    <h3>Avg. Time</h3>
                    <div className="value">{stats.averageTimeMinutes} min</div>
                </div>
            </div>

            <div className="performance-distribution">
                <h3>Performance Distribution</h3>
                <div className="distribution-bar">
                    <div className="excellent" style={{width: `${stats.distribution.excellent}%`}}>
                        Excellent: {stats.distribution.excellent}
                    </div>
                    <div className="good" style={{width: `${stats.distribution.good}%`}}>
                        Good: {stats.distribution.good}
                    </div>
                    <div className="needs-improvement" style={{width: `${stats.distribution.needsImprovement}%`}}>
                        Needs Work: {stats.distribution.needsImprovement}
                    </div>
                </div>
            </div>
        </div>
    );
}

export default StudentDashboard;
```

### 3. Complete Exam Flow with Result Saving

```javascript
// components/TakeExam.jsx
import { useState, useEffect } from 'react';

function TakeExam() {
    const [examStarted, setExamStarted] = useState(false);
    const [attemptToken, setAttemptToken] = useState(null);
    const [questions, setQuestions] = useState([]);
    const [answers, setAnswers] = useState({});
    const [results, setResults] = useState(null);
    const [savedResultId, setSavedResultId] = useState(null);

    // Start exam
    const startExam = async () => {
        try {
            const response = await fetch('http://localhost/takeExam.php', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'startExam',
                    examType: 'both', // mcq, voice, or both
                    numberOfQuestions: 10
                })
            });

            const data = await response.json();
            
            if (data.success) {
                setAttemptToken(data.data.attemptToken);
                setQuestions(data.data.questions);
                setExamStarted(true);
            }
        } catch (error) {
            console.error('Error starting exam:', error);
        }
    };

    // Submit exam
    const submitExam = async () => {
        try {
            // Convert answers object to array format
            const answersArray = Object.keys(answers).map(questionId => ({
                question_id: parseInt(questionId),
                selected_index: answers[questionId]
            }));

            const response = await fetch('http://localhost/takeExam.php', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'submitAnswers',
                    attemptToken: attemptToken,
                    answers: answersArray
                })
            });

            const data = await response.json();
            
            if (data.success) {
                setResults(data.data.summary);
                setSavedResultId(data.data.examResultId); // ← Database record ID!
                
                console.log('Exam saved to database with ID:', data.data.examResultId);
                
                // Show success message
                alert(`Exam submitted! Score: ${data.data.summary.percentage}%`);
            }
        } catch (error) {
            console.error('Error submitting exam:', error);
        }
    };

    // Handle answer selection
    const handleAnswerSelect = (questionId, optionIndex) => {
        setAnswers(prev => ({
            ...prev,
            [questionId]: optionIndex
        }));
    };

    if (results) {
        return (
            <div className="exam-results">
                <h2>Exam Results</h2>
                <div className="results-summary">
                    <p>Total Questions: {results.total}</p>
                    <p>Correct: {results.correct}</p>
                    <p>Incorrect: {results.incorrect}</p>
                    <p className="percentage">Score: {results.percentage}%</p>
                    
                    {savedResultId && (
                        <p className="saved-info">
                            ✓ Results saved to your history (ID: {savedResultId})
                        </p>
                    )}
                </div>
                
                <button onClick={() => window.location.href = '/exam-history'}>
                    View All Results
                </button>
            </div>
        );
    }

    if (!examStarted) {
        return (
            <div className="exam-start">
                <h2>Ready to take the exam?</h2>
                <button onClick={startExam}>Start Exam</button>
            </div>
        );
    }

    return (
        <div className="exam-container">
            <h2>Exam in Progress</h2>
            {questions.map((question, index) => (
                <div key={question.id} className="question">
                    <h3>Question {index + 1}</h3>
                    <p>{question.questionText}</p>
                    
                    <div className="options">
                        {question.options.map((option, optionIndex) => (
                            <label key={optionIndex}>
                                <input
                                    type="radio"
                                    name={`question-${question.id}`}
                                    value={optionIndex}
                                    onChange={() => handleAnswerSelect(question.id, optionIndex)}
                                    checked={answers[question.id] === optionIndex}
                                />
                                {option.text}
                            </label>
                        ))}
                    </div>
                </div>
            ))}
            
            <button onClick={submitExam}>Submit Exam</button>
        </div>
    );
}

export default TakeExam;
```

### 4. Get Recent Results (for Quick View)

```javascript
// components/RecentExams.jsx
import { useState, useEffect } from 'react';

function RecentExams() {
    const [recentResults, setRecentResults] = useState([]);

    useEffect(() => {
        fetchRecentResults();
    }, []);

    const fetchRecentResults = async () => {
        try {
            const response = await fetch('http://localhost/getExamResults.php?action=getRecentResults&limit=5', {
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                setRecentResults(data.data.results);
            }
        } catch (error) {
            console.error('Error fetching recent results:', error);
        }
    };

    return (
        <div className="recent-exams">
            <h3>Recent Exams</h3>
            <ul>
                {recentResults.map(result => (
                    <li key={result.id}>
                        <span>{new Date(result.submittedAt).toLocaleDateString()}</span>
                        <span>{result.percentage.toFixed(1)}%</span>
                        <span>{Math.round(result.timeTaken / 60)} min</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default RecentExams;
```

---

## API Endpoints Summary

### 1. **Start Exam**
```
POST /takeExam.php
Body: { action: "startExam", examType: "both", numberOfQuestions: 10 }
```

### 2. **Submit Exam** (Saves to database automatically)
```
POST /takeExam.php
Body: { 
    action: "submitAnswers", 
    attemptToken: "...", 
    answers: [...] 
}
Response: { examResultId: 123, summary: {...} }
```

### 3. **Get All Results**
```
GET /getExamResults.php?action=getResults&limit=50&offset=0
```

### 4. **Get Statistics**
```
GET /getExamResults.php?action=getStatistics
```

### 5. **Get Single Result**
```
GET /getExamResults.php?action=getResult&id=123
```

### 6. **Get Recent Results**
```
GET /getExamResults.php?action=getRecentResults&limit=5
```

---

## Important Notes

1. **Always include `credentials: 'include'`** in fetch requests to send session cookies
2. **The `examResultId`** is returned when exam is submitted - use it to reference the saved record
3. **User must be logged in** as a student for results to be saved
4. **Mock exams** have `examId: null`, real exams have a number

---

## Error Handling

```javascript
const fetchWithErrorHandling = async (url, options = {}) => {
    try {
        const response = await fetch(url, {
            credentials: 'include',
            ...options
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Request failed');
        }
        
        return data.data;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
};

// Usage
try {
    const results = await fetchWithErrorHandling(
        'http://localhost/getExamResults.php?action=getResults'
    );
    setResults(results.results);
} catch (error) {
    alert('Failed to load results: ' + error.message);
}
```

---

## Complete Example with Context

```javascript
// context/ExamContext.jsx
import { createContext, useContext, useState } from 'react';

const ExamContext = createContext();

export function ExamProvider({ children }) {
    const [currentExam, setCurrentExam] = useState(null);
    const [examResults, setExamResults] = useState([]);

    const startExam = async (examType, numberOfQuestions) => {
        const response = await fetch('http://localhost/takeExam.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'startExam',
                examType,
                numberOfQuestions
            })
        });

        const data = await response.json();
        if (data.success) {
            setCurrentExam(data.data);
        }
        return data;
    };

    const submitExam = async (attemptToken, answers) => {
        const response = await fetch('http://localhost/takeExam.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'submitAnswers',
                attemptToken,
                answers
            })
        });

        const data = await response.json();
        if (data.success) {
            // Refresh exam results list
            loadExamResults();
        }
        return data;
    };

    const loadExamResults = async () => {
        const response = await fetch('http://localhost/getExamResults.php?action=getResults', {
            credentials: 'include'
        });

        const data = await response.json();
        if (data.success) {
            setExamResults(data.data.results);
        }
    };

    return (
        <ExamContext.Provider value={{
            currentExam,
            examResults,
            startExam,
            submitExam,
            loadExamResults
        }}>
            {children}
        </ExamContext.Provider>
    );
}

export const useExam = () => useContext(ExamContext);
```

---

## Testing the Integration

1. **Start your React app**: `npm run dev`
2. **Make sure XAMPP is running**: Apache and MySQL
3. **Login as a student** first
4. **Take an exam** using the TakeExam component
5. **Check the response** - look for `examResultId` in console
6. **View results** using ExamHistory or Dashboard components
7. **Verify in database**: Check `exam_results` table

---

**All set! Your frontend can now interact with the exam results system.** 🎉
