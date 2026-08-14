package com.example

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.slideInVertically
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.LazyRow
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
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.ui.theme.CyanAccent
import com.example.ui.theme.PinkAccent
import com.example.ui.theme.PurpleAccent
import androidx.lifecycle.compose.collectAsStateWithLifecycle

@Composable
fun LoginScreen(onLoginSuccess: () -> Unit) {
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }

    Box(modifier = Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background), contentAlignment = Alignment.Center) {
        Column(
            modifier = Modifier.padding(32.dp).widthIn(max = 400.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(Icons.Filled.School, contentDescription = "Logo", tint = PinkAccent, modifier = Modifier.size(64.dp))
            Spacer(modifier = Modifier.height(16.dp))
            Text("AULASYNC", fontSize = 28.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onBackground)
            Text("El futuro de la planificación educativa", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 14.sp)
            Spacer(modifier = Modifier.height(48.dp))

            Card(
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                shape = RoundedCornerShape(16.dp),
                elevation = CardDefaults.cardElevation(defaultElevation = 8.dp)
            ) {
                Column(modifier = Modifier.padding(24.dp)) {
                    Text("Bienvenido de vuelta", fontWeight = FontWeight.Bold, fontSize = 20.sp, color = MaterialTheme.colorScheme.onSurface)
                    Spacer(modifier = Modifier.height(24.dp))
                    OutlinedTextField(
                        value = email,
                        onValueChange = { email = it },
                        label = { Text("Correo Electrónico") },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp)
                    )
                    Spacer(modifier = Modifier.height(16.dp))
                    OutlinedTextField(
                        value = password,
                        onValueChange = { password = it },
                        label = { Text("Contraseña") },
                        visualTransformation = PasswordVisualTransformation(),
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp)
                    )
                    Spacer(modifier = Modifier.height(24.dp))
                    Button(
                        onClick = onLoginSuccess,
                        modifier = Modifier.fillMaxWidth().height(50.dp),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = PinkAccent)
                    ) {
                        Text("Entrar Ahora", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Color.White)
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DashboardScreen(viewModel: NovaViewModel) {
    val activities by viewModel.activities.collectAsStateWithLifecycle()
    
    val nextClass = activities.firstOrNull { it.type == "CLASE" }
    val nextEvaluation = activities.firstOrNull { it.type == "EVALUACIÓN" }
    
    var selectedActivityForDetails by remember { mutableStateOf<ActivityItem?>(null) }
    var showClassDetails by remember { mutableStateOf(false) }

    Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
        Text("Alertas Proactivas", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onBackground)
        Spacer(modifier = Modifier.height(16.dp))
        
        Card(
            modifier = Modifier.fillMaxWidth().heightIn(min = 80.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
            shape = RoundedCornerShape(16.dp)
        ) {
            Row(modifier = Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Default.NotificationsActive, contentDescription = null, tint = PinkAccent, modifier = Modifier.size(32.dp))
                Spacer(modifier = Modifier.width(16.dp))
                Column {
                    Text("Mañana tienes el examen de Revolución Industrial.", color = MaterialTheme.colorScheme.onSurface, fontWeight = FontWeight.Bold)
                    Text("Daniel y Vicente tienen tareas pendientes.", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 14.sp)
                }
            }
        }
        
        Spacer(modifier = Modifier.height(32.dp))

        Text("Próximos Eventos", fontSize = 24.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onBackground)
        Spacer(modifier = Modifier.height(16.dp))
        
        LazyRow(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            item {
                if (nextClass != null) {
                    Card(
                        modifier = Modifier
                            .width(280.dp)
                            .height(160.dp)
                            .clickable {
                                selectedActivityForDetails = nextClass
                                showClassDetails = true
                            },
                        shape = RoundedCornerShape(24.dp),
                        colors = CardDefaults.cardColors(containerColor = Color.Transparent)
                    ) {
                        Box(
                            modifier = Modifier
                                .fillMaxSize()
                                .background(Brush.linearGradient(listOf(MaterialTheme.colorScheme.surface, PurpleAccent.copy(alpha = 0.3f))))
                                .padding(20.dp)
                        ) {
                            Column(modifier = Modifier.fillMaxSize(), verticalArrangement = Arrangement.SpaceBetween) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Icon(Icons.Filled.Class, contentDescription = "Clase", tint = PurpleAccent)
                                    Spacer(modifier = Modifier.width(8.dp))
                                    Text("Próxima Clase", fontWeight = FontWeight.Bold, color = PurpleAccent)
                                }
                                Column {
                                    Text(nextClass.title, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurface, maxLines = 2)
                                    Spacer(modifier = Modifier.height(8.dp))
                                    Text("${nextClass.date} • ${nextClass.type}", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                            }
                        }
                    }
                }
            }
            item {
                if (nextEvaluation != null) {
                    Card(
                        modifier = Modifier
                            .width(280.dp)
                            .height(160.dp)
                            .clickable {
                                selectedActivityForDetails = nextEvaluation
                                showClassDetails = true
                            },
                        shape = RoundedCornerShape(24.dp),
                        colors = CardDefaults.cardColors(containerColor = Color.Transparent)
                    ) {
                        Box(
                            modifier = Modifier
                                .fillMaxSize()
                                .background(Brush.linearGradient(listOf(MaterialTheme.colorScheme.surface, PinkAccent.copy(alpha = 0.3f))))
                                .padding(20.dp)
                        ) {
                            Column(modifier = Modifier.fillMaxSize(), verticalArrangement = Arrangement.SpaceBetween) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Icon(Icons.Filled.Assignment, contentDescription = "Actividad", tint = PinkAccent)
                                    Spacer(modifier = Modifier.width(8.dp))
                                    Text("Próxima Actividad", fontWeight = FontWeight.Bold, color = PinkAccent)
                                }
                                Column {
                                    Text(nextEvaluation.title, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurface, maxLines = 2)
                                    Spacer(modifier = Modifier.height(8.dp))
                                    Text("Entrega: ${nextEvaluation.date} • Ponderación: ${nextEvaluation.weight}%", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                            }
                        }
                    }
                }
            }
        }
        
        Spacer(modifier = Modifier.height(32.dp))
        
        Text("Rendimiento del Curso", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onBackground)
        Spacer(modifier = Modifier.height(16.dp))
        
        Card(
            modifier = Modifier.fillMaxWidth().height(140.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
            shape = RoundedCornerShape(24.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxSize().padding(24.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text("Promedio General", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 14.sp)
                    Spacer(modifier = Modifier.height(4.dp))
                    Text("Muy Bueno", color = CyanAccent, fontWeight = FontWeight.Bold, fontSize = 24.sp)
                    Spacer(modifier = Modifier.height(8.dp))
                    Text("+2.4% este mes", color = PurpleAccent, fontSize = 12.sp, fontWeight = FontWeight.Medium)
                }
                
                Box(contentAlignment = Alignment.Center, modifier = Modifier.size(80.dp)) {
                    CircularProgressIndicator(
                        progress = { 0.84f },
                        modifier = Modifier.fillMaxSize(),
                        color = CyanAccent,
                        trackColor = MaterialTheme.colorScheme.surfaceVariant,
                        strokeWidth = 8.dp,
                    )
                    Text("84%", color = MaterialTheme.colorScheme.onSurface, fontWeight = FontWeight.Bold, fontSize = 20.sp)
                }
            }
        }
    }
    
    if (showClassDetails && selectedActivityForDetails != null) {
        ClassBottomSheet(
            activity = selectedActivityForDetails!!, 
            viewModel = viewModel, 
            onDismissRequest = { showClassDetails = false }
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MyCourseScreen(viewModel: NovaViewModel) {
    val students by viewModel.students.collectAsStateWithLifecycle()
    var selectedStudent by remember { mutableStateOf<Student?>(null) }
    var showAuditSheet by remember { mutableStateOf(false) }

    Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
        Text("Mi Curso", fontSize = 24.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onBackground)
        Text("Listado de alumnos", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 14.sp)
        Spacer(modifier = Modifier.height(16.dp))
        
        Card(
            modifier = Modifier.fillMaxWidth().weight(1f),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
            shape = RoundedCornerShape(16.dp)
        ) {
            LazyColumn(modifier = Modifier.padding(16.dp)) {
                items(students) { student ->
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable {
                                selectedStudent = student
                                showAuditSheet = true
                            }
                            .padding(vertical = 12.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(modifier = Modifier.size(40.dp).clip(CircleShape).background(MaterialTheme.colorScheme.surfaceVariant), contentAlignment = Alignment.Center) {
                                Text(student.name.take(1).uppercase(), color = MaterialTheme.colorScheme.onSurface, fontWeight = FontWeight.Bold)
                            }
                            Spacer(modifier = Modifier.width(16.dp))
                            Column {
                                Text(student.name, fontWeight = FontWeight.Medium, color = MaterialTheme.colorScheme.onSurface, fontSize = 18.sp)
                                if (student.badges.isNotEmpty()) {
                                    Row {
                                        student.badges.forEach { badge ->
                                            Text(badge, fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.padding(end = 4.dp))
                                        }
                                    }
                                }
                            }
                        }
                        Text("${student.accumAverage}", color = CyanAccent, fontWeight = FontWeight.Bold, fontSize = 18.sp)
                    }
                    // No divider for clean flat look
                }
            }
        }
    }

    if (showAuditSheet && selectedStudent != null) {
        var isGeneratingPDF by remember { mutableStateOf(false) }

        ModalBottomSheet(
            onDismissRequest = { showAuditSheet = false },
            containerColor = MaterialTheme.colorScheme.surface,
            modifier = Modifier.fillMaxHeight(0.9f)
        ) {
            val grades = viewModel.getGradesForStudent(selectedStudent!!.id)
            Column(modifier = Modifier.padding(16.dp)) {
                Text(selectedStudent!!.name, fontSize = 24.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurface)
                Text("Promedio: ${selectedStudent!!.accumAverage}", fontSize = 16.sp, color = CyanAccent)
                Spacer(modifier = Modifier.height(16.dp))

                Button(
                    onClick = {
                        isGeneratingPDF = true
                    },
                    modifier = Modifier.fillMaxWidth(),
                    colors = ButtonDefaults.buttonColors(containerColor = PurpleAccent),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    if (isGeneratingPDF) {
                        CircularProgressIndicator(color = Color.White, modifier = Modifier.size(24.dp))
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Redactando informe...", color = Color.White)
                    } else {
                        Icon(Icons.Default.PictureAsPdf, contentDescription = null, tint = Color.White)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Generar Informe (PDF) con IA", color = Color.White, fontWeight = FontWeight.Bold)
                    }
                }
                
                LaunchedEffect(isGeneratingPDF) {
                    if (isGeneratingPDF) {
                        kotlinx.coroutines.delay(2000)
                        isGeneratingPDF = false
                        // Faking success, here we'd show a snackbar or open PDF.
                    }
                }

                Spacer(modifier = Modifier.height(24.dp))
                
                Text("Historial de Notas", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurface)
                LazyColumn(modifier = Modifier.weight(1f)) {
                    items(grades) { (activity, grade) ->
                        Row(
                            modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp),
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Column(modifier = Modifier.weight(1f)) {
                                Text(activity.title, color = MaterialTheme.colorScheme.onSurface, fontSize = 14.sp)
                                Text("${activity.weight}%", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp)
                            }
                            Text(grade?.score?.toString() ?: "-", color = if (grade != null) PurpleAccent else MaterialTheme.colorScheme.onSurfaceVariant, fontWeight = FontWeight.Bold)
                        }
                    }
                    
                    item {
                        Spacer(modifier = Modifier.height(24.dp))
                        Text("Anécdotas & Observaciones", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurface)
                        Spacer(modifier = Modifier.height(8.dp))
                        if (selectedStudent!!.anecdotes.isEmpty()) {
                            Text("No hay observaciones registradas.", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 14.sp)
                        } else {
                            selectedStudent!!.anecdotes.forEach { anecdote ->
                                Card(
                                    modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f))
                                ) {
                                    Text(anecdote, modifier = Modifier.padding(12.dp), color = MaterialTheme.colorScheme.onSurface, fontSize = 14.sp)
                                }
                            }
                        }
                    }
                }
                Spacer(modifier = Modifier.height(16.dp))
            }
        }
    }
}
