package com.example

import androidx.lifecycle.ViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update

// --- MODELS (Supabase DB Equivalents) ---
data class Student(
    val id: String, 
    val name: String, 
    val accumAverage: Float = 0f,
    val badges: List<String> = emptyList(),
    val anecdotes: List<String> = emptyList()
)

data class ActivityItem(
    val id: String, 
    val title: String, 
    val weight: Int, 
    val date: String,
    val type: String = "CLASE"
)

data class Grade(
    val studentId: String, 
    val activityId: String, 
    var score: Float, 
    var status: String = "draft", // 'draft' or 'published'
    val publishedAt: Long? = null
)

// --- VIEWMODEL ---
// Simulates the Supabase connection and state management
class NovaViewModel : ViewModel() {
    private val _themeIsDark = MutableStateFlow(true)
    val themeIsDark: StateFlow<Boolean> = _themeIsDark.asStateFlow()

    private val _students = MutableStateFlow<List<Student>>(emptyList())
    val students: StateFlow<List<Student>> = _students.asStateFlow()

    private val _activities = MutableStateFlow<List<ActivityItem>>(emptyList())
    val activities: StateFlow<List<ActivityItem>> = _activities.asStateFlow()

    private val _grades = MutableStateFlow<List<Grade>>(emptyList())
    val grades: StateFlow<List<Grade>> = _grades.asStateFlow()

    init {
        loadMockData()
    }

    fun toggleTheme() {
        _themeIsDark.update { !it }
    }

    // Load initial fast data directly mimicking Supabase tables
    private fun loadMockData() {
        _students.value = listOf(
            Student("s1", "Daniel", 5.25f, listOf("⏱️ Puntual", "💡 Curioso"), listOf("Mostró mucho interés en la clase dictada.", "Faltó a la clase pasada.")),
            Student("s2", "Maria", 6.6f, listOf("⭐ Responsable", "🥇 Destacada"), listOf("María lideró su grupo de trabajo hoy.")),
            Student("s3", "Vicente", 5.25f, emptyList(), listOf("Sin material para la actividad de Arte."))
        )
        
        _activities.value = listOf(
            ActivityItem("a1", "Arte barroco: Conceptos clave", 0, "2026-05-04", "CLASE"),
            ActivityItem("a2", "Revolución industrial: Teoría fundamental", 25, "2026-05-06", "EVALUACIÓN"),
            ActivityItem("a3", "Barroco", 10, "2026-05-11", "CLASE"),
            ActivityItem("a4", "Revolución industrial: Repaso teórico", 0, "2026-05-13", "CLASE")
        )
        
        // Mock some grades
        _grades.value = listOf(
            Grade("s1", "a2", 5.5f, "published", System.currentTimeMillis()),
            Grade("s2", "a2", 7.0f, "published", System.currentTimeMillis()),
            Grade("s3", "a2", 6.0f, "published", System.currentTimeMillis())
        )
    }

    fun getGradesForStudent(studentId: String): List<Pair<ActivityItem, Grade?>> {
        return _activities.value.map { activity ->
            val grade = _grades.value.find { it.studentId == studentId && it.activityId == activity.id }
            Pair(activity, grade)
        }
    }
    
    fun addAnecdote(studentId: String, anecdote: String) {
        _students.update { current ->
            current.map { if (it.id == studentId) it.copy(anecdotes = it.anecdotes + anecdote) else it }
        }
    }
    
    fun updateGrade(studentId: String, activityId: String, score: Float, status: String) {
        _grades.update { currentGrades ->
            val newGrades = currentGrades.toMutableList()
            val existingIdx = newGrades.indexOfFirst { it.studentId == studentId && it.activityId == activityId }
            if (existingIdx >= 0) {
                newGrades[existingIdx] = newGrades[existingIdx].copy(score = score, status = status)
            } else {
                newGrades.add(Grade(studentId, activityId, score, status, if(status == "published") System.currentTimeMillis() else null))
            }
            newGrades
        }
        recalculateAverages()
    }

    private fun recalculateAverages() {
        _students.update { currentStudents ->
            currentStudents.map { student ->
                val studentGrades = _grades.value.filter { it.studentId == student.id && it.status == "published" }
                val totalWeight = studentGrades.mapNotNull { grade -> _activities.value.find { it.id == grade.activityId }?.weight }.sum()
                val weightedSum = studentGrades.sumOf { grade ->
                    val weight = _activities.value.find { it.id == grade.activityId }?.weight ?: 0
                    (grade.score * weight).toDouble()
                }
                val avg = if (totalWeight > 0) (weightedSum / totalWeight).toFloat() else 0f
                student.copy(accumAverage = avg)
            }
        }
    }
}
