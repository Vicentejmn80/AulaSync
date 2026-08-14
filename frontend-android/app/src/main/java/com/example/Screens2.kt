package com.example

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.ui.theme.CyanAccent
import com.example.ui.theme.PinkAccent
import com.example.ui.theme.PurpleAccent
import androidx.lifecycle.compose.collectAsStateWithLifecycle

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ActivitiesScreen(viewModel: NovaViewModel) {
    val activities by viewModel.activities.collectAsStateWithLifecycle()
    var selectedActivity by remember { mutableStateOf<ActivityItem?>(null) }
    var showGradingSheet by remember { mutableStateOf(false) }

    Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
        Text("Clases & Actividades", fontSize = 24.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onBackground)
        Text("Lecciones teóricas y evaluaciones", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 14.sp)
        Spacer(modifier = Modifier.height(16.dp))
        
        Card(
            modifier = Modifier.fillMaxWidth().weight(1f),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
            shape = RoundedCornerShape(16.dp)
        ) {
            LazyColumn(modifier = Modifier.padding(16.dp)) {
                items(activities) { activity ->
                    Row(
                        modifier = Modifier.fillMaxWidth().padding(vertical = 12.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Box(
                                    modifier = Modifier.clip(RoundedCornerShape(4.dp)).background(if(activity.type == "EVALUACIÓN") PinkAccent else PurpleAccent).padding(horizontal = 4.dp, vertical = 2.dp)
                                ) {
                                    Text(activity.type, color = Color.White, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                                }
                                Spacer(modifier = Modifier.width(8.dp))
                                Text(activity.date, color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp)
                            }
                            Spacer(modifier = Modifier.height(4.dp))
                            Text(activity.title, color = MaterialTheme.colorScheme.onSurface, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                        }
                        
                        if (activity.type == "EVALUACIÓN") {
                            Button(
                                onClick = {
                                    selectedActivity = activity
                                    showGradingSheet = true
                                },
                                colors = ButtonDefaults.buttonColors(containerColor = PurpleAccent),
                                shape = RoundedCornerShape(8.dp)
                            ) {
                                Text("Cargar Notas")
                            }
                        } else {
                            Box(modifier = Modifier.padding(8.dp)) {
                                Text("${activity.weight}%", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp)
                            }
                        }
                    }
                    HorizontalDivider(color = MaterialTheme.colorScheme.surfaceVariant)
                }
            }
        }
    }

    if (showGradingSheet && selectedActivity != null) {
        ModalBottomSheet(
            onDismissRequest = { showGradingSheet = false },
            containerColor = MaterialTheme.colorScheme.surface
        ) {
            val students by viewModel.students.collectAsStateWithLifecycle()
            val allGrades by viewModel.grades.collectAsStateWithLifecycle()
            
            Column(modifier = Modifier.padding(16.dp)) {
                Text("Cargar Notas: ${selectedActivity!!.title}", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurface)
                Spacer(modifier = Modifier.height(16.dp))
                LazyColumn {
                    items(students) { student ->
                        val currentGrade = allGrades.find { it.studentId == student.id && it.activityId == selectedActivity!!.id }
                        var inputGrade by remember { mutableStateOf(currentGrade?.score?.toString() ?: "") }
                        
                        Row(
                            modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Text(student.name, color = MaterialTheme.colorScheme.onSurface, modifier = Modifier.weight(1f))
                            
                            OutlinedTextField(
                                value = inputGrade,
                                onValueChange = { 
                                    inputGrade = it 
                                    it.toFloatOrNull()?.let { score ->
                                        viewModel.updateGrade(student.id, selectedActivity!!.id, score, "draft")
                                    }
                                },
                                modifier = Modifier.width(100.dp).height(50.dp),
                                textStyle = LocalTextStyle.current.copy(textAlign = androidx.compose.ui.text.style.TextAlign.Center, color = MaterialTheme.colorScheme.onSurface),
                                shape = RoundedCornerShape(8.dp)
                            )
                        }
                    }
                }
                Spacer(modifier = Modifier.height(16.dp))
                Button(
                    onClick = {
                        // Mark all as published
                        students.forEach { st ->
                            val grade = allGrades.find { it.studentId == st.id && it.activityId == selectedActivity!!.id }
                            if (grade != null) {
                                viewModel.updateGrade(st.id, selectedActivity!!.id, grade.score, "published")
                            }
                        }
                        showGradingSheet = false
                    },
                    modifier = Modifier.fillMaxWidth(),
                    colors = ButtonDefaults.buttonColors(containerColor = CyanAccent)
                ) {
                    Text("Publicar Notas", color = MaterialTheme.colorScheme.background)
                }
                Spacer(modifier = Modifier.height(32.dp))
            }
        }
    }
}
