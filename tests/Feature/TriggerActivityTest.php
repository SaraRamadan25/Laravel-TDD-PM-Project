<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Facades\Tests\Setup\ProjectFactory;
use Tests\TestCase;

class TriggerActivityTest extends TestCase
{
   use RefreshDatabase;

    /** @test */
    function creating_a_project()
    {
        $project = ProjectFactory::create();

        $this->assertCount(1, $project->activity);

        tap($project->activity->last(), function ($activity) {
            $this->assertEquals('created_project', $activity->description);

            $this->assertNull($activity->changes);
        });
    }

    /** @test */
    function updating_a_project()
    {
        $project = ProjectFactory::create();
        $originalTitle = $project->title;

        $project->update(['title' => 'Changed']);

        $this->assertCount(2, $project->activity);

        tap($project->activity->last(), function ($activity) use ($originalTitle) {
            $this->assertEquals('updated', $activity->description);

            $expected = [
                'before' => ['title' => $originalTitle],
                'after' => ['title' => 'Changed']
            ];

            $this->assertEquals($expected, $activity->changes);
        });
    }
    /** @test  */
    function creating_a_new_task(){
        $project = ProjectFactory::create();

        $project->addTask('Some Task');

        $this->assertCount(2, $project->activity);
        tap($project->activity->last(),function ($activity){
           $this->assertEquals('created_task',$activity->description);
            $this->assertInstanceOf(Task::class, $activity->subject);
            $this->assertEquals('Some Task',$activity->subject->body);


        });
    }
    /** @test  */
    function completing_a_task()
    {
        $project = ProjectFactory::withTasks(1)->create();
        $this->actingAs($project->owner)->patch($project->tasks[0]->path(),[
                'body'=>'foobar',
                'completed'=> true
        ]);
        $this->assertCount(3, $project->activity);

        tap($project->activity->last(),function ($activity){
            $this->assertEquals('completed_task',$activity->description);
            $this->assertInstanceOf(Task::class, $activity->subject);

        });
    }
    /** @test  */
    function incompleting_a_task()
    {
        $project = ProjectFactory::withTasks(1)->create();

        $this->actingAs($project->owner)
            ->patch($project->tasks[0]->path(),[
            'body'=>'foobar',
            'completed'=> true
        ]);
        $this->assertCount(3, $project->activity);

        $this->patch($project->tasks[0]->path(),[
            'body'=>'foobar',
            'completed'=> false
        ]);
        // we use a fresh as we are loading the activity relationship
        // and here when we call it again, we using the already loaded obj
        //new query to fetch the activity
        $project->refresh();

        $this->assertCount(4, $project->activity);
        $this->assertEquals('incompleted_task', $project->activity->last()->description);
    }
    /** @test  */
    function deleting_a_task()
{
    $project = ProjectFactory::withTasks(1)->create();

    $project->tasks[0]->delete();

    // how many pieces of activities  should we have
    // one for creating a project, one for creating a task, one for deleting a task
    $this->assertCount(3, $project->activity);

}
}